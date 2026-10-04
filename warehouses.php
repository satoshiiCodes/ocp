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
// $swal_data carries the message the actions file wants shown. It is created here,
// before the actions run, so the markup below always finds it defined.
$swal_data = [];

// The add branch stores its message in the session and redirects, so it has to be read
// back here: on the redirected GET the actions file does not run at all.
if (isset($_SESSION['swal_data'])) {
    $swal_data = $_SESSION['swal_data'];
    unset($_SESSION['swal_data']);
}

// All of this page's actions live in one file: deleting, updating and adding a
// warehouse. The forms post back to this page, so it is pulled in before anything
// is read or rendered. On a rejected submission it leaves $swal_data set and the
// markup below reopens the right form.
if (!defined('OCP_WAREHOUSES_ACTIONS_RAN')) {
    require __DIR__ . '/actions/warehouses-actions.php';
}

// All of this page's fetching lives in one file: it returns the variables the
// markup below needs, which are unpacked into this scope.
$ocp_endpoint = require __DIR__ . '/api/warehouses-endpoint.php';
foreach ($ocp_endpoint as $ocp_key => $ocp_value) {
    ${$ocp_key} = $ocp_value;
}
unset($ocp_endpoint, $ocp_key, $ocp_value);


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
        <link rel="icon" type="image/png" href="assets/images/logo/OCP.png">
        <link href="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/style.min.css" rel="stylesheet" />
        <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
                            <h1 class="page-title">Warehouses</h1>
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                <li class="breadcrumb-item active">Warehouses</li>
                            </ol>
                        </div>
                        
                        <!-- Display all warehouses in a table -->
                        <div class="card mb-6">
                            <div class="card-header flex justify-between items-center">
                                <div>
                                    <i class="fas fa-table mr-1"></i>
                                    All Warehouses
                                </div>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addWarehouseModal">
                                    <i class="fas fa-plus mr-1"></i> Add Warehouse
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
                                                    <div class="inline-flex gap-2" role="group">
                                                        <form method="POST" class="inline">
                                                            <input type="hidden" name="view_id" value="<?php echo $warehouse['id']; ?>">
                                                            <button type="button" class="btn btn-secondary btn-sm view-btn" data-warehouse='<?php echo json_encode($warehouse); ?>'>
                                                                <i class="fas fa-eye"></i>
                                                            </button>
                                                        </form>
                                                        <form method="POST" class="inline">
                                                            <input type="hidden" name="edit_id" value="<?php echo $warehouse['id']; ?>">
                                                            <button type="button" class="btn btn-primary btn-sm edit-btn" data-warehouse='<?php echo json_encode($warehouse); ?>'>
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                        </form>
                                                        <form method="POST" class="inline" onsubmit="return confirmDelete(event, this)">
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
                            <div class="form-floating mb-4">
                                <input type="text" class="form-control" id="warehouse_name" name="warehouse_name" 
                                       placeholder="Warehouse Name" 
                                       value="<?php echo isset($form_values['warehouse_name']) ? htmlspecialchars($form_values['warehouse_name']) : ''; ?>" 
                                       required maxlength="255">
                                <label for="warehouse_name">Warehouse Name <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <input type="text" class="form-control" id="location" name="location" 
                                       placeholder="Location"
                                       value="<?php echo isset($form_values['location']) ? htmlspecialchars($form_values['location']) : ''; ?>" 
                                       required maxlength="255">
                                <label for="location">Location <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <input type="number" class="form-control" id="capacity" name="capacity" 
                                       placeholder="Capacity"
                                       value="<?php echo isset($form_values['capacity']) ? htmlspecialchars($form_values['capacity']) : ''; ?>" 
                                       min="1">
                                <label for="capacity">Capacity</label>
                                <div class="form-text ml-1">Total storage capacity.</div>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <input type="text" class="form-control" id="manager" name="manager" 
                                       placeholder="Manager"
                                       value="<?php echo isset($form_values['manager']) ? htmlspecialchars($form_values['manager']) : ''; ?>" 
                                       maxlength="255">
                                <label for="manager">Manager</label>
                            </div>
                            
                            <div class="form-floating mb-4">
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
                        <div class="mb-4">
                            <label class="form-label fw-bold">Warehouse Name:</label>
                            <p id="view_warehouse_name" class="text-slate-800"></p>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-bold">Location:</label>
                            <p id="view_location" class="text-slate-800"></p>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-bold">Capacity:</label>
                            <p id="view_capacity" class="text-slate-800"></p>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-bold">Manager:</label>
                            <p id="view_manager" class="text-slate-800"></p>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-bold">Phone:</label>
                            <p id="view_phone" class="text-slate-800"></p>
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
                            <div class="form-floating mb-4">
                                <input type="text" class="form-control" id="edit_warehouse_name" name="warehouse_name" 
                                       placeholder="Warehouse Name" required maxlength="255">
                                <label for="edit_warehouse_name">Warehouse Name <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <input type="text" class="form-control" id="edit_location" name="location" 
                                       placeholder="Location" required maxlength="255">
                                <label for="edit_location">Location <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <input type="number" class="form-control" id="edit_capacity" name="capacity" 
                                       placeholder="Capacity" min="1">
                                <label for="edit_capacity">Capacity</label>
                                <div class="form-text ml-1">Total storage capacity.</div>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <input type="text" class="form-control" id="edit_manager" name="manager" 
                                       placeholder="Manager" maxlength="255">
                                <label for="edit_manager">Manager</label>
                            </div>
                            
                            <div class="form-floating mb-4">
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

        <script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>
        <?php
        /* Data island consumed by assets/js/warehouses.js. */
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
        /* pOST = isset($_POST["update_id"]) ? $_POST["update_id"] : "" [guarded] */
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($swal_data) && $swal_data['icon'] === 'error' && isset($_POST['update_id'])) {
            $__ocp_data["pOST"] = isset($_POST["update_id"]) ? $_POST["update_id"] : "";
        }
        /* pOST2 = isset($_POST["warehouse_name"]) ? $_POST["warehouse_name"] : "" [guarded] */
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($swal_data) && $swal_data['icon'] === 'error' && isset($_POST['update_id'])) {
            $__ocp_data["pOST2"] = isset($_POST["warehouse_name"]) ? $_POST["warehouse_name"] : "";
        }
        /* pOST3 = isset($_POST["location"]) ? $_POST["location"] : "" [guarded] */
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($swal_data) && $swal_data['icon'] === 'error' && isset($_POST['update_id'])) {
            $__ocp_data["pOST3"] = isset($_POST["location"]) ? $_POST["location"] : "";
        }
        /* pOST4 = isset($_POST["capacity"]) ? $_POST["capacity"] : "" [guarded] */
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($swal_data) && $swal_data['icon'] === 'error' && isset($_POST['update_id'])) {
            $__ocp_data["pOST4"] = isset($_POST["capacity"]) ? $_POST["capacity"] : "";
        }
        /* pOST5 = isset($_POST["manager"]) ? $_POST["manager"] : "" [guarded] */
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($swal_data) && $swal_data['icon'] === 'error' && isset($_POST['update_id'])) {
            $__ocp_data["pOST5"] = isset($_POST["manager"]) ? $_POST["manager"] : "";
        }
        /* pOST6 = isset($_POST["phone"]) ? $_POST["phone"] : "" [guarded] */
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($swal_data) && $swal_data['icon'] === 'error' && isset($_POST['update_id'])) {
            $__ocp_data["pOST6"] = isset($_POST["phone"]) ? $_POST["phone"] : "";
        }
        /* Flags for the script. It is a separate request, so it cannot test this
         * page's variables itself; these answer for it. Each is false on an ordinary
         * load, so nothing is shown unless there is something to show. */
        $__ocp_data["hasMessage"] = (!empty($swal_data));
        $__ocp_data["isError"] = (($swal_data['icon'] ?? '') === 'error');
        $__ocp_data["reopenAddModal"] = ($_SERVER['REQUEST_METHOD'] === 'POST' && ($swal_data['icon'] ?? '') === 'error' && !isset($_POST['delete_id']) && !isset($_POST['update_id']));
        $__ocp_data["reopenEditModal"] = ($_SERVER['REQUEST_METHOD'] === 'POST' && ($swal_data['icon'] ?? '') === 'error' && isset($_POST['update_id']));
        ocp_page_data("warehouses", $__ocp_data);
        unset($__ocp_data);
        ?>
        <script src="<?php echo ocp_asset('assets/js/app.js'); ?>"></script>
        <script src="assets/js/warehouses.js.php"></script>
    </body>
</html>
