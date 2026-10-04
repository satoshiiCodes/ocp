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
// The SweetAlert2 message the actions file wants shown. These are created here,
// before the actions run, so the markup below always finds them defined.
$swal_message = '';
$swal_type = ''; // success, error, warning, info
$swal_title = '';

// All of this page's actions live in one file: deleting, updating and adding a
// gasoline supplier. The forms post back to this page, so it is pulled in before
// anything is read or rendered. A rejected submission leaves the message set and
// the markup below shows it; a success redirects.
if (!defined('OCP_GASOLINE_SUPPLIERS_ACTIONS_RAN')) {
    require __DIR__ . '/actions/gasoline_suppliers-actions.php';
}

// A redirect can carry the message back on the query string.
// Check for SweetAlert2 message in URL parameters (for redirects)
if (isset($_GET['swal_message']) && isset($_GET['swal_type']) && isset($_GET['swal_title'])) {
    $swal_title = urldecode($_GET['swal_title']);
    $swal_message = urldecode($_GET['swal_message']);
    $swal_type = $_GET['swal_type'];
}

// All of this page's fetching lives in one file: it returns the variables the
// markup below needs, which are unpacked into this scope.
$ocp_endpoint = require __DIR__ . '/api/gasoline_suppliers-endpoint.php';
foreach ($ocp_endpoint as $ocp_key => $ocp_value) {
    ${$ocp_key} = $ocp_value;
}
unset($ocp_endpoint, $ocp_key, $ocp_value);
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>Gasoline Suppliers - OCP Construction</title>
        <link rel="icon" type="image/png" href="assets/images/logo/OCP.png">
        <link href="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/style.min.css" rel="stylesheet" />
        <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
        <!-- SweetAlert2 CSS -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
        <link href="assets/css/app.css" rel="stylesheet" />
        <link href="assets/css/app.build.css" rel="stylesheet" />
    </head>
    <body class="sb-nav-fixed">
        <?php include 'includes/top_bar.php';?>
        <div id="layoutSidenav">
            <?php include 'includes/side_menu.php';?>
            <div id="layoutSidenav_content" class="sb-content">
                <main>
                    <div class="w-full px-6">
                        <div class="mb-6">
                            <h1 class="page-title">Gasoline Suppliers</h1>
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                <li class="breadcrumb-item active">Gasoline Suppliers</li>
                            </ol>
                        </div>
                        
                        <!-- Display all gasoline suppliers in a table -->
                        <div class="card mb-6">
                            <div class="card-header flex items-center justify-between">
                                <div>
                                    <i class="fas fa-gas-pump mr-1"></i>
                                    All Gasoline Suppliers
                                </div>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSupplierModal">
                                    <i class="fas fa-plus mr-1"></i> Add Gasoline Supplier
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
                                                    <span class="badge badge-<?php echo $supplier['is_active'] ? 'success' : 'neutral'; ?>">
                                                        <?php echo $supplier['is_active'] ? 'Active' : 'Inactive'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="inline-flex items-center gap-1" role="group">
                                                        <form method="POST" class="inline">
                                                            <button type="button" class="btn btn-sm bg-info-600 text-white" data-bs-toggle="modal" data-bs-target="#viewSupplierModal<?php echo $supplier['id']; ?>">
                                                                <i class="fas fa-eye"></i>
                                                            </button>
                                                        </form>
                                                        <form method="POST" class="inline">
                                                            <button type="button" class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editSupplierModal<?php echo $supplier['id']; ?>">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                        </form>
                                                        <form method="POST" class="inline">
                                                            <button type="button" class="btn btn-danger btn-sm delete-supplier-btn" 
                                                                    data-supplier-id="<?php echo $supplier['id']; ?>"
                                                                    data-supplier-name="<?php echo htmlspecialchars($supplier['supplier_name']); ?>">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                    
                                                    <!-- View Modal -->
                                                    <div class="modal" id="viewSupplierModal<?php echo $supplier['id']; ?>" tabindex="-1" aria-labelledby="viewSupplierModalLabel<?php echo $supplier['id']; ?>" aria-hidden="true">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title" id="viewSupplierModalLabel<?php echo $supplier['id']; ?>">Supplier Details</h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    <div class="mb-4">
                                                                        <strong>Supplier Name:</strong> <?php echo htmlspecialchars($supplier['supplier_name']); ?>
                                                                    </div>
                                                                    <div class="mb-4">
                                                                        <strong>Supplier Type:</strong> <?php echo htmlspecialchars($supplier['supplier_type']); ?>
                                                                    </div>
                                                                    <div class="mb-4">
                                                                        <strong>Contact Person:</strong> <?php echo htmlspecialchars($supplier['contact_person']); ?>
                                                                    </div>
                                                                    <div class="mb-4">
                                                                        <strong>Phone:</strong> <?php echo htmlspecialchars($supplier['phone']); ?>
                                                                    </div>
                                                                    <div class="mb-4">
                                                                        <strong>Email:</strong> <?php echo htmlspecialchars($supplier['email']); ?>
                                                                    </div>
                                                                    <div class="mb-4">
                                                                        <strong>Address:</strong> <?php echo htmlspecialchars($supplier['address']); ?>
                                                                    </div>
                                                                    <div class="mb-4">
                                                                        <strong>Status:</strong> 
                                                                        <span class="badge badge-<?php echo $supplier['is_active'] ? 'success' : 'neutral'; ?>">
                                                                            <?php echo $supplier['is_active'] ? 'Active' : 'Inactive'; ?>
                                                                        </span>
                                                                    </div>
                                                                    <?php if (!empty($supplier['created_at'])): ?>
                                                                    <div class="mb-4">
                                                                        <strong>Created:</strong> <?php echo date('m-d-Y g:i A', strtotime($supplier['created_at'])); ?>
                                                                    </div>
                                                                    <?php endif; ?>
                                                                    <?php if (!empty($supplier['updated_at'])): ?>
                                                                    <div class="mb-4">
                                                                        <strong>Last Updated:</strong> <?php echo date('m-d-Y g:i A', strtotime($supplier['updated_at'])); ?>
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
                                                    <div class="modal" id="editSupplierModal<?php echo $supplier['id']; ?>" tabindex="-1" aria-labelledby="editSupplierModalLabel<?php echo $supplier['id']; ?>" aria-hidden="true">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title" id="editSupplierModalLabel<?php echo $supplier['id']; ?>">Edit Supplier</h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                </div>
                                                                <form method="POST" action="" id="editForm<?php echo $supplier['id']; ?>">
                                                                    <div class="modal-body">
                                                                        <input type="hidden" name="supplier_id" value="<?php echo $supplier['id']; ?>">
                                                                        <div class="form-floating mb-4">
                                                                            <input type="text" class="form-control" id="edit_supplier_name_<?php echo $supplier['id']; ?>" name="supplier_name" 
                                                                                   value="<?php echo htmlspecialchars($supplier['supplier_name']); ?>" 
                                                                                   required maxlength="255" placeholder="Supplier Name">
                                                                            <label for="edit_supplier_name_<?php echo $supplier['id']; ?>">Supplier Name <span class="text-danger">*</span></label>
                                                                        </div>
                                                                        
                                                                        <div class="form-floating mb-4">
                                                                            <input type="text" class="form-control" id="edit_supplier_type_<?php echo $supplier['id']; ?>" name="supplier_type" 
                                                                                   value="Fuel" disabled readonly placeholder="Supplier Type">
                                                                            <label for="edit_supplier_type_<?php echo $supplier['id']; ?>">Supplier Type</label>
                                                                            <input type="hidden" name="supplier_type" value="Fuel">
                                                                        </div>
                                                                        
                                                                        <div class="form-floating mb-4">
                                                                            <input type="text" class="form-control" id="edit_contact_person_<?php echo $supplier['id']; ?>" name="contact_person" 
                                                                                   value="<?php echo htmlspecialchars($supplier['contact_person']); ?>" 
                                                                                   maxlength="255" placeholder="Contact Person">
                                                                            <label for="edit_contact_person_<?php echo $supplier['id']; ?>">Contact Person</label>
                                                                        </div>
                                                                        
                                                                        <div class="form-floating mb-4">
                                                                            <input type="text" class="form-control" id="edit_phone_<?php echo $supplier['id']; ?>" name="phone" 
                                                                                   value="<?php echo htmlspecialchars($supplier['phone']); ?>" 
                                                                                   maxlength="20" placeholder="Phone">
                                                                            <label for="edit_phone_<?php echo $supplier['id']; ?>">Phone</label>
                                                                        </div>
                                                                        
                                                                        <div class="form-floating mb-4">
                                                                            <input type="email" class="form-control" id="edit_email_<?php echo $supplier['id']; ?>" name="email" 
                                                                                   value="<?php echo htmlspecialchars($supplier['email']); ?>" 
                                                                                   maxlength="100" placeholder="Email">
                                                                            <label for="edit_email_<?php echo $supplier['id']; ?>">Email</label>
                                                                        </div>
                                                                        
                                                                        <div class="form-floating mb-4">
                                                                            <textarea class="form-control" id="edit_address_<?php echo $supplier['id']; ?>" name="address" 
                                                                                      style="height: 100px" placeholder="Address"><?php echo htmlspecialchars($supplier['address']); ?></textarea>
                                                                            <label for="edit_address_<?php echo $supplier['id']; ?>">Address</label>
                                                                        </div>
                                                                        
                                                                        <div class="form-check mb-4">
                                                                            <input class="form-check-input" type="checkbox" id="edit_is_active_<?php echo $supplier['id']; ?>" name="is_active" value="1" <?php echo $supplier['is_active'] ? 'checked' : ''; ?>>
                                                                            <label for="edit_is_active_<?php echo $supplier['id']; ?>">Active Supplier</label>
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
        <div class="modal" id="addSupplierModal" tabindex="-1" aria-labelledby="addSupplierModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addSupplierModalLabel">Add New Gasoline Supplier</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="" id="addSupplierForm">
                        <div class="modal-body">
                            <div class="form-floating mb-4">
                                <input type="text" class="form-control" id="supplier_name" name="supplier_name" 
                                       value="" 
                                       required maxlength="255" placeholder="Supplier Name">
                                <label for="supplier_name">Supplier Name <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <input type="text" class="form-control" id="supplier_type" name="supplier_type" 
                                       value="Fuel" disabled readonly placeholder="Supplier Type">
                                <label for="supplier_type">Supplier Type</label>
                                <input type="hidden" name="supplier_type" value="Fuel">
                            </div>
                            
                            <div class="form-floating mb-4">
                                <input type="text" class="form-control" id="contact_person" name="contact_person" 
                                       value="" 
                                       maxlength="255" placeholder="Contact Person">
                                <label for="contact_person">Contact Person</label>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <input type="text" class="form-control" id="phone" name="phone" 
                                       value="" 
                                       maxlength="20" placeholder="Phone">
                                <label for="phone">Phone</label>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="" 
                                       maxlength="100" placeholder="Email">
                                <label for="email">Email</label>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <textarea class="form-control" id="address" name="address" 
                                          style="height: 100px" placeholder="Address"></textarea>
                                <label for="address">Address</label>
                            </div>
                            
                            <div class="form-check mb-4">
                                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" checked>
                                <label for="is_active">Active Supplier</label>
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

        <script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>
        <!-- SweetAlert2 JS -->
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
        <?php
        /* Data island consumed by assets/js/gasoline_suppliers.js. */
        $__ocp_data = [];
        /* swalTitle = $swal_title [guarded] */
        if (!empty($swal_message)) {
            $__ocp_data["swalTitle"] = $swal_title;
        }
        /* swalMessage = $swal_message [guarded] */
        if (!empty($swal_message)) {
            $__ocp_data["swalMessage"] = $swal_message;
        }
        /* swalType = $swal_type [guarded] */
        if (!empty($swal_message)) {
            $__ocp_data["swalType"] = $swal_type;
        }
        /* Flags for the script. It is a separate request, so it cannot test this
         * page's variables itself; these answer for it. Each is false on an ordinary
         * load, so nothing is shown unless there is something to show. */
        $__ocp_data["hasMessage"] = (!empty($swal_message));
        /* Flags for the script. It is a separate request, so it cannot test this
         * page's variables itself; these answer for it. Each is false on an ordinary
         * load, so nothing is shown unless there is something to show. */
        $__ocp_data["reopenAddModal"] = ($_SERVER['REQUEST_METHOD'] === 'POST' && ($swal_type ?? '') === 'error' && !isset($_POST['update_supplier']) && !isset($_POST['delete_supplier']));
        ocp_page_data("gasoline_suppliers", $__ocp_data);
        unset($__ocp_data);
        ?>
        <script src="<?php echo ocp_asset('assets/js/app.js'); ?>"></script>
        <script src="assets/js/gasoline_suppliers.js.php"></script>
    </body>
</html>
