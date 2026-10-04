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
// $swal_data carries the message the actions file wants shown, and the form
// values for a retry. It is created here, before the actions run, so the markup
// below always finds it defined.
$message = '';
$message_type = ''; // success or danger
$swal_data = []; // For SweetAlert2 data

// All of this page's actions live in one file: deleting, editing and adding a
// spare-parts supplier. The forms post back to this page, so it is pulled in
// before anything is read or rendered.
if (!defined('OCP_SPARE_PARTS_SUPPLIERS_ACTIONS_RAN')) {
    require __DIR__ . '/actions/spare_parts_suppliers-actions.php';
}

// All of this page's fetching lives in one file: it returns the variables the
// markup below needs, which are unpacked into this scope. The two message
// entries are only taken when the endpoint actually set one, so a message the
// actions file produced is not wiped out.
$ocp_endpoint = require __DIR__ . '/api/spare_parts_suppliers-endpoint.php';
foreach ($ocp_endpoint as $ocp_key => $ocp_value) {
    if (in_array($ocp_key, ['swal_data', 'message', 'message_type', 'error_message'], true) && empty($ocp_value)) {
        continue;
    }
    ${$ocp_key} = $ocp_value;
}
unset($ocp_endpoint, $ocp_key, $ocp_value);
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>Spare Parts Suppliers - OCP Construction</title>
        <link rel="icon" type="image/png" href="assets/images/logo/OCP.png">
        <link href="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/style.min.css" rel="stylesheet" />
        <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
        <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
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
                            <h1 class="page-title">Spare Parts Suppliers</h1>
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item active">Spare Parts Suppliers</li>
                            </ol>
                        </div>
                        
                        <!-- Display all suppliers in a table -->
                        <div class="card mb-6">
                            <div class="card-header flex items-center justify-between">
                                <div>
                                    <i class="fas fa-table mr-1"></i>
                                    All Spare Parts Suppliers
                                </div>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSupplierModal">
                                    <i class="fas fa-plus mr-1"></i> Add Supplier
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
                                                <td><?php echo htmlspecialchars(ocp_datetime_mdy($supplier['created_at'])); ?></td>
                                                <td><?php echo htmlspecialchars(ocp_datetime_mdy($supplier['updated_at'])); ?></td>
                                                <td>
                                                     <div class="inline-flex items-center gap-2" role="group">
                                                        <form method="POST" class="inline">
                                                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#viewSupplierModal" 
                                                                data-id="<?php echo $supplier['id']; ?>"
                                                                data-supplier_name="<?php echo htmlspecialchars($supplier['supplier_name']); ?>"
                                                                data-contact_person="<?php echo htmlspecialchars($supplier['contact_person']); ?>"
                                                                data-email="<?php echo htmlspecialchars($supplier['email']); ?>"
                                                                data-phone="<?php echo htmlspecialchars($supplier['phone']); ?>"
                                                                data-address="<?php echo htmlspecialchars($supplier['address']); ?>"
                                                                data-created_at="<?php echo htmlspecialchars(ocp_datetime_mdy($supplier['created_at'])); ?>"
                                                                data-updated_at="<?php echo htmlspecialchars(ocp_datetime_mdy($supplier['updated_at'])); ?>">
                                                                <i class="fas fa-eye"></i>
                                                            </button>
                                                        </form>
                                                        <form method="POST" class="inline">
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
                                                        <form method="POST" class="inline">
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
        <div class="modal" id="addSupplierModal" tabindex="-1" aria-labelledby="addSupplierModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addSupplierModalLabel">Add New Supplier</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="" id="addSupplierForm">
                        <div class="modal-body">
                            <div class="form-floating mb-4">
                                <input type="text" class="form-control" id="supplier_name" name="supplier_name" 
                                       value="<?php echo isset($_POST['supplier_name']) && !isset($_POST['edit_id']) ? htmlspecialchars($_POST['supplier_name']) : ''; ?>" 
                                       required maxlength="255" placeholder="Supplier Name">
                                <label for="supplier_name">Supplier Name <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                                <div class="min-w-0">
                                    <div class="form-floating mb-4">
                                        <input type="text" class="form-control" id="contact_person" name="contact_person" 
                                               value="<?php echo isset($_POST['contact_person']) && !isset($_POST['edit_id']) ? htmlspecialchars($_POST['contact_person']) : ''; ?>" 
                                               maxlength="100" placeholder="Contact Person">
                                        <label for="contact_person">Contact Person</label>
                                    </div>
                                </div>
                                <div class="min-w-0">
                                    <div class="form-floating mb-4">
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?php echo isset($_POST['email']) && !isset($_POST['edit_id']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                                               maxlength="100" placeholder="Email">
                                        <label for="email">Email</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <input type="text" class="form-control" id="phone" name="phone" 
                                       value="<?php echo isset($_POST['phone']) && !isset($_POST['edit_id']) ? htmlspecialchars($_POST['phone']) : ''; ?>" 
                                       maxlength="20" placeholder="Phone">
                                <label for="phone">Phone</label>
                            </div>
                            
                            <div class="form-floating mb-4">
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
        <div class="modal" id="editSupplierModal" tabindex="-1" aria-labelledby="editSupplierModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editSupplierModalLabel">Edit Supplier</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="" id="editSupplierForm">
                        <input type="hidden" name="edit_id" id="edit_id">
                        <div class="modal-body">
                            <div class="form-floating mb-4">
                                <input type="text" class="form-control" id="edit_supplier_name" name="supplier_name" 
                                       required maxlength="255" placeholder="Supplier Name">
                                <label for="edit_supplier_name">Supplier Name <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                                <div class="min-w-0">
                                    <div class="form-floating mb-4">
                                        <input type="text" class="form-control" id="edit_contact_person" name="contact_person" 
                                               maxlength="100" placeholder="Contact Person">
                                        <label for="edit_contact_person">Contact Person</label>
                                    </div>
                                </div>
                                <div class="min-w-0">
                                    <div class="form-floating mb-4">
                                        <input type="email" class="form-control" id="edit_email" name="email" 
                                               maxlength="100" placeholder="Email">
                                        <label for="edit_email">Email</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <input type="text" class="form-control" id="edit_phone" name="phone" 
                                       maxlength="20" placeholder="Phone">
                                <label for="edit_phone">Phone</label>
                            </div>
                            
                            <div class="form-floating mb-4">
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
        <div class="modal" id="viewSupplierModal" tabindex="-1" aria-labelledby="viewSupplierModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="viewSupplierModalLabel">Supplier Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="grid grid-cols-1 gap-6 mb-4">
                            <div class="min-w-0">
                                <strong>Supplier Name:</strong>
                                <p id="view_supplier_name" class="text-muted"></p>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2 mb-4">
                            <div class="min-w-0">
                                <strong>Contact Person:</strong>
                                <p id="view_contact_person" class="text-muted"></p>
                            </div>
                            <div class="min-w-0">
                                <strong>Email:</strong>
                                <p id="view_email" class="text-muted"></p>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2 mb-4">
                            <div class="min-w-0">
                                <strong>Phone:</strong>
                                <p id="view_phone" class="text-muted"></p>
                            </div>
                            <div class="min-w-0">
                                <strong>Address:</strong>
                                <p id="view_address" class="text-muted"></p>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2 mb-4">
                            <div class="min-w-0">
                                <strong>Created At:</strong>
                                <p id="view_created_at" class="text-muted"></p>
                            </div>
                            <div class="min-w-0">
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

        <script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
        <?php
        /* Data island consumed by assets/js/spare_parts_suppliers.js. */
        $__ocp_data = [];
        /* swalDataTitle = $swal_data['title'] [guarded] */
        if (!empty($swal_data)) {
            $__ocp_data["swalDataTitle"] = $swal_data['title'];
        }
        /* swalDataText = $swal_data['text'] [guarded] */
        if (!empty($swal_data)) {
            $__ocp_data["swalDataText"] = $swal_data['text'];
        }
        /* swalDataIcon = $swal_data['icon'] [guarded] */
        if (!empty($swal_data)) {
            $__ocp_data["swalDataIcon"] = $swal_data['icon'];
        }
        /* Flags for the script. It is a separate request, so it cannot test this
         * page's variables itself; these answer for it. Each is false on an ordinary
         * load, so nothing is shown unless there is something to show. */
        $__ocp_data["hasMessage"] = (!empty($swal_data));
        $__ocp_data["isError"] = (($swal_data['icon'] ?? '') === 'error');
        $__ocp_data["isSuccess"] = (($swal_data['icon'] ?? '') === 'success');
        $__ocp_data["postedEditId"] = ($_POST['edit_id'] ?? '');
        $__ocp_data["postedDeleteId"] = ($_POST['delete_id'] ?? '');
        /* Flags for the script. It is a separate request, so it cannot test this
         * page's variables itself; these answer for it. Each is false on an ordinary
         * load, so nothing is shown unless there is something to show. */
        $__ocp_data["isSuccessOnPost"] = (($swal_data['icon'] ?? '') === 'success' && $_SERVER['REQUEST_METHOD'] === 'POST');
        $__ocp_data["wasPost"] = ($_SERVER['REQUEST_METHOD'] === 'POST');
        ocp_page_data("spare_parts_suppliers", $__ocp_data);
        unset($__ocp_data);
        ?>
        <script src="<?php echo ocp_asset('assets/js/app.js'); ?>"></script>
        <script src="assets/js/spare_parts_suppliers.js.php"></script>
    </body>
</html>
