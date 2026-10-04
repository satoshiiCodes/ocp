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
// $swal_data and the form-repopulation values carry what the actions file wants
// shown. They are created here, before the actions run, so the markup below
// always finds them defined.
$message = '';
$message_type = ''; // success or danger
$swal_data = []; // For SweetAlert2 data

// All of this page's actions live in one file: deleting, editing and adding a
// gasoline tank. The forms post back to this page, so it is pulled in before
// anything is read or rendered.
if (!defined('OCP_GASOLINE_TANK_ACTIONS_RAN')) {
    require __DIR__ . '/actions/gasoline_tank-actions.php';
}

// Get session data for alerts and form repopulation
if (isset($_SESSION['swal_data'])) {
    $swal_data = $_SESSION['swal_data'];
    unset($_SESSION['swal_data']);
}

$form_data = [];
$form_type = '';
if (isset($_SESSION['form_data'])) {
    $form_data = $_SESSION['form_data'];
    unset($_SESSION['form_data']);
}
if (isset($_SESSION['form_type'])) {
    $form_type = $_SESSION['form_type'];
    unset($_SESSION['form_type']);
}

// All of this page's fetching lives in one file: it returns the variables the
// markup below needs, which are unpacked into this scope. A fetch failure comes
// back as swal_data too, so it is not lost.
$ocp_endpoint = require __DIR__ . '/api/gasoline_tank-endpoint.php';
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
        <title>Gasoline Tanks - OCP Construction</title>
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
                            <h1 class="page-title">Gasoline Tanks</h1>
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                <li class="breadcrumb-item active">Gasoline Tanks</li>
                            </ol>
                        </div>
                        
                        <!-- Display all tanks in a table -->
                        <div class="card mb-6">
                            <div class="card-header flex items-center justify-between">
                                <div>
                                    <i class="fas fa-gas-pump mr-1"></i>
                                    All Gasoline Tanks
                                </div>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTankModal">
                                    <i class="fas fa-plus mr-1"></i> Add Tank
                                </button>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($tanks)): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped table-hover" id="datatablesSimple">
                                        <thead>
                                            <tr>
                                                <th>Tank Name</th>
                                                <th>Location</th>
                                                <th>Capacity (Liters)</th>
                                                <th>Description</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($tanks as $tank): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($tank['tank_name']); ?></td>
                                                <td><?php echo htmlspecialchars($tank['location']); ?></td>
                                                <td><?php echo number_format($tank['capacity_liters'], 2); ?></td>
                                                <td><?php echo htmlspecialchars($tank['description'] ?? 'N/A'); ?></td>
                                                <td>
                                                    <span class="badge badge-<?php echo $tank['is_active'] ? 'success' : 'neutral'; ?>">
                                                        <?php echo $tank['is_active'] ? 'Active' : 'Inactive'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="inline-flex items-center gap-1" role="group">
                                                        <form method="POST" class="inline">
                                                            <button type="button" class="btn btn-sm bg-info-600 text-white" data-bs-toggle="modal" data-bs-target="#viewTankModal<?php echo $tank['id']; ?>">
                                                                <i class="fas fa-eye"></i>
                                                            </button>
                                                        </form>
                                                        <form method="POST" class="inline">
                                                            <button type="button" class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editTankModal<?php echo $tank['id']; ?>">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                        </form>
                                                        <form method="POST" class="inline" id="deleteForm<?php echo $tank['id']; ?>">
                                                            <input type="hidden" name="tank_id" value="<?php echo $tank['id']; ?>">
                                                            <button type="button" class="btn btn-danger btn-sm delete-tank-btn" data-tank-id="<?php echo $tank['id']; ?>" data-tank-name="<?php echo htmlspecialchars($tank['tank_name']); ?>" data-location="<?php echo htmlspecialchars($tank['location']); ?>">
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
                                <p class="text-center">No gasoline tanks found. Add your first tank using the button above.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </main>
                <?php include 'includes/footer.php';?>
            </div>
        </div>
        
        <!-- Add Tank Modal -->
        <div class="modal" id="addTankModal" tabindex="-1" aria-labelledby="addTankModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addTankModalLabel">Add New Gasoline Tank</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="" id="addTankForm">
                        <div class="modal-body">
                            <div class="form-floating mb-4">
                                <input type="text" class="form-control" id="tank_name" name="tank_name" 
                                       placeholder="Tank Name" 
                                       value="<?php echo ($form_type === 'add' && isset($form_data['tank_name'])) ? htmlspecialchars($form_data['tank_name']) : ''; ?>" 
                                       required maxlength="100">
                                <label for="tank_name">Tank Name <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <input type="text" class="form-control" id="location" name="location" 
                                       placeholder="Location"
                                       value="<?php echo ($form_type === 'add' && isset($form_data['location'])) ? htmlspecialchars($form_data['location']) : ''; ?>" 
                                       required maxlength="255">
                                <label for="location">Location <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <input type="number" class="form-control" id="capacity_liters" name="capacity_liters" 
                                       placeholder="Capacity in Liters"
                                       value="<?php echo ($form_type === 'add' && isset($form_data['capacity_liters'])) ? htmlspecialchars($form_data['capacity_liters']) : ''; ?>" 
                                       min="0.01" step="0.01" required>
                                <label for="capacity_liters">Capacity (Liters) <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <textarea class="form-control" id="description" name="description" 
                                          placeholder="Description" 
                                          style="height: 100px"><?php echo ($form_type === 'add' && isset($form_data['description'])) ? htmlspecialchars($form_data['description']) : ''; ?></textarea>
                                <label for="description">Description</label>
                            </div>
                            
                            <div class="form-check mb-4">
                                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" 
                                    <?php echo ($form_type === 'add' && isset($form_data['is_active']) && $form_data['is_active']) ? 'checked' : 'checked'; ?>>
                                <label for="is_active">Active</label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Add Tank</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modals for each tank (View, Edit) -->
        <?php foreach ($tanks as $tank): ?>
        <!-- View Tank Modal -->
        <div class="modal" id="viewTankModal<?php echo $tank['id']; ?>" tabindex="-1" aria-labelledby="viewTankModalLabel<?php echo $tank['id']; ?>" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="viewTankModalLabel<?php echo $tank['id']; ?>">Tank Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-4">
                            <strong>Tank Name:</strong> <?php echo htmlspecialchars($tank['tank_name']); ?>
                        </div>
                        <div class="mb-4">
                            <strong>Location:</strong> <?php echo htmlspecialchars($tank['location']); ?>
                        </div>
                        <div class="mb-4">
                            <strong>Capacity:</strong> <?php echo number_format($tank['capacity_liters'], 2); ?> Liters
                        </div>
                        <div class="mb-4">
                            <strong>Description:</strong> <?php echo htmlspecialchars($tank['description'] ?? 'N/A'); ?>
                        </div>
                        <div class="mb-4">
                            <strong>Status:</strong> 
                            <span class="badge badge-<?php echo $tank['is_active'] ? 'success' : 'neutral'; ?>">
                                <?php echo $tank['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </div>
                        <div class="mb-4">
                            <strong>Created:</strong> <?php echo date('m-d-Y g:i A', strtotime($tank['created_at'])); ?>
                        </div>
                        <?php if ($tank['updated_at'] != $tank['created_at']): ?>
                        <div class="mb-4">
                            <strong>Last Updated:</strong> <?php echo date('m-d-Y g:i A', strtotime($tank['updated_at'])); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Tank Modal -->
        <div class="modal" id="editTankModal<?php echo $tank['id']; ?>" tabindex="-1" aria-labelledby="editTankModalLabel<?php echo $tank['id']; ?>" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editTankModalLabel<?php echo $tank['id']; ?>">Edit Gasoline Tank</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="" id="editTankForm<?php echo $tank['id']; ?>">
                        <div class="modal-body">
                            <input type="hidden" name="tank_id" value="<?php echo $tank['id']; ?>">
                            <div class="form-floating mb-4">
                                <input type="text" class="form-control" id="edit_tank_name_<?php echo $tank['id']; ?>" name="tank_name" 
                                       placeholder="Tank Name" 
                                       value="<?php echo ($form_type === 'edit' && isset($form_data['tank_id']) && $form_data['tank_id'] == $tank['id']) ? htmlspecialchars($form_data['tank_name']) : htmlspecialchars($tank['tank_name']); ?>" 
                                       required maxlength="100">
                                <label for="edit_tank_name_<?php echo $tank['id']; ?>">Tank Name <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <input type="text" class="form-control" id="edit_location_<?php echo $tank['id']; ?>" name="location" 
                                       placeholder="Location"
                                       value="<?php echo ($form_type === 'edit' && isset($form_data['tank_id']) && $form_data['tank_id'] == $tank['id']) ? htmlspecialchars($form_data['location']) : htmlspecialchars($tank['location']); ?>" 
                                       required maxlength="255">
                                <label for="edit_location_<?php echo $tank['id']; ?>">Location <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <input type="number" class="form-control" id="edit_capacity_liters_<?php echo $tank['id']; ?>" name="capacity_liters" 
                                       placeholder="Capacity in Liters"
                                       value="<?php echo ($form_type === 'edit' && isset($form_data['tank_id']) && $form_data['tank_id'] == $tank['id']) ? htmlspecialchars($form_data['capacity_liters']) : $tank['capacity_liters']; ?>" 
                                       min="0.01" step="0.01" required>
                                <label for="edit_capacity_liters_<?php echo $tank['id']; ?>">Capacity (Liters) <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <textarea class="form-control" id="edit_description_<?php echo $tank['id']; ?>" name="description" 
                                          placeholder="Description" 
                                          style="height: 100px"><?php echo ($form_type === 'edit' && isset($form_data['tank_id']) && $form_data['tank_id'] == $tank['id']) ? htmlspecialchars($form_data['description']) : htmlspecialchars($tank['description'] ?? ''); ?></textarea>
                                <label for="edit_description_<?php echo $tank['id']; ?>">Description</label>
                            </div>
                            
                            <div class="form-check mb-4">
                                <input class="form-check-input" type="checkbox" id="edit_is_active_<?php echo $tank['id']; ?>" name="is_active" 
                                    <?php echo ($form_type === 'edit' && isset($form_data['tank_id']) && $form_data['tank_id'] == $tank['id']) ? (isset($form_data['is_active']) ? 'checked' : '') : ($tank['is_active'] ? 'checked' : ''); ?>>
                                <label for="edit_is_active_<?php echo $tank['id']; ?>">Active</label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" name="edit_tank" class="btn btn-primary">Update Tank</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
        <?php
        /* Data island consumed by assets/js/gasoline_tank.js. */
        $__ocp_data = [];
        /* swalDataIcon = $swal_data['icon'] [guarded] */
        if (!empty($swal_data)) {
            $__ocp_data["swalDataIcon"] = $swal_data['icon'];
        }
        /* swalDataTitle = $swal_data['title'] [guarded] */
        if (!empty($swal_data)) {
            $__ocp_data["swalDataTitle"] = $swal_data['title'];
        }
        /* swalDataText = $swal_data['text'] [guarded] */
        if (!empty($swal_data)) {
            $__ocp_data["swalDataText"] = $swal_data['text'];
        }
        /* formDataTankId = $form_data['tank_id'] [guarded] */
        if (!empty($swal_data) && $swal_data['icon'] === 'error' && $form_type === 'edit' && isset($form_data['tank_id'])) {
            $__ocp_data["formDataTankId"] = $form_data['tank_id'];
        }
        /* Flags for the script. It is a separate request, so it cannot test this
         * page's variables itself; these answer for it. Each is false on an ordinary
         * load, so nothing is shown unless there is something to show. */
        $__ocp_data["hasMessage"] = (!empty($swal_data));
        $__ocp_data["isError"] = (($swal_data['icon'] ?? '') === 'error');
        $__ocp_data["formIsAdd"] = (($form_type ?? '') === 'add');
        $__ocp_data["formIsEdit"] = (($form_type ?? '') === 'edit' && !empty($form_data['tank_id']));
        ocp_page_data("gasoline_tank", $__ocp_data);
        unset($__ocp_data);
        ?>
        <script src="<?php echo ocp_asset('assets/js/app.js'); ?>"></script>
        <script src="assets/js/gasoline_tank.js.php"></script>
    </body>
</html>
