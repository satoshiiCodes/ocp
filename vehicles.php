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
// $show_modal tells the markup which modal to reopen when an action was rejected.
// The actions file sets it; it starts false for a plain page load.
$show_modal = false;

// All of this page's actions live in one file: deleting a vehicle (a GET) and
// adding or editing one (POSTs). The form posts back to this page, so it is
// pulled in before anything is read or rendered.
if (!defined('OCP_VEHICLES_ACTIONS_RAN')) {
    require __DIR__ . '/actions/vehicles-actions.php';
}

// All of this page's fetching lives in one file: it returns the variables the
// markup below needs, which are unpacked into this scope.
$ocp_endpoint = require __DIR__ . '/api/vehicles-endpoint.php';
foreach ($ocp_endpoint as $ocp_key => $ocp_value) {
    ${$ocp_key} = $ocp_value;
}
unset($ocp_endpoint, $ocp_key, $ocp_value);

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
        <link rel="icon" type="image/png" href="assets/images/logo/OCP.png">
        <link href="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/style.min.css" rel="stylesheet" />
        <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
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
                            <h1 class="page-title">Vehicles</h1>
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                <li class="breadcrumb-item active">Vehicles</li>
                            </ol>
                        </div>
                        
                        <!-- Display all vehicles in a table -->
                        <div class="card mb-6">
                            <div class="card-header flex justify-between items-center">
                                <div>
                                    <i class="fas fa-car mr-1"></i>
                                    All Vehicles
                                </div>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addVehicleModal">
                                    <i class="fas fa-plus mr-1"></i> Add Vehicle
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
                                                    <span class="badge badge-neutral">
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
                                                    <span class="badge badge-success">Active</span>
                                                    <?php else: ?>
                                                    <span class="badge badge-neutral">Inactive</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="inline-flex gap-2">
                                                        <form method="POST" action="view_vehicle.php" style="display: inline;">
                                                            <input type="hidden" name="vehicle_id" value="<?php echo $vehicle['id']; ?>">
                                                            <button type="submit" class="btn btn-sm bg-info-600 text-white" title="View">
                                                                <i class="fas fa-eye"></i>
                                                            </button>
                                                        </form>
                                                        <button class="btn btn-sm btn-warning edit-vehicle-btn" 
                                                                title="Edit" 
                                                                data-id="<?php echo $vehicle['id']; ?>"
                                                                data-name="<?php echo htmlspecialchars($vehicle['vehicle_name']); ?>"
                                                                data-plate="<?php echo htmlspecialchars($vehicle['plate_number']); ?>"
                                                                data-fuel="<?php echo $vehicle['fuel_type']; ?>"
                                                                data-description="<?php echo htmlspecialchars($vehicle['description']); ?>"
                                                                data-active="<?php echo $vehicle['is_active']; ?>">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-danger delete-vehicle-btn" 
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
                            <div class="form-floating mb-4">
                                <input type="text" class="form-control" id="vehicle_name" name="vehicle_name" 
                                       placeholder="Vehicle Name" 
                                       value="<?php echo isset($_POST['vehicle_name']) ? htmlspecialchars($_POST['vehicle_name']) : ''; ?>" 
                                       required maxlength="100">
                                <label for="vehicle_name">Vehicle Name <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <input type="text" class="form-control" id="plate_number" name="plate_number" 
                                       placeholder="Plate Number"
                                       value="<?php echo isset($_POST['plate_number']) ? htmlspecialchars($_POST['plate_number']) : ''; ?>" 
                                       required maxlength="20">
                                <label for="plate_number">Plate Number <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="mb-4">
                                <label for="fuel_type" class="form-label">Fuel Type</label>
                                <select class="form-select" id="fuel_type" name="fuel_type">
                                    <option value="gasoline" <?php echo (isset($_POST['fuel_type']) && $_POST['fuel_type'] == 'gasoline') ? 'selected' : ''; ?>>Gasoline</option>
                                    <option value="diesel" <?php echo (isset($_POST['fuel_type']) && $_POST['fuel_type'] == 'diesel') ? 'selected' : ''; ?>>Diesel</option>
                                </select>
                            </div>
                            
                            <div class="mb-4">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" 
                                          rows="3" placeholder="Vehicle description (optional)"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                            </div>
                            
                            <div class="form-check mb-4">
                                <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" checked>
                                <label for="is_active">Active</label>
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
                            <div class="form-floating mb-4">
                                <input type="text" class="form-control" id="edit_vehicle_name" name="vehicle_name" 
                                       placeholder="Vehicle Name" required maxlength="100">
                                <label for="edit_vehicle_name">Vehicle Name <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <input type="text" class="form-control" id="edit_plate_number" name="plate_number" 
                                       placeholder="Plate Number" required maxlength="20">
                                <label for="edit_plate_number">Plate Number <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="mb-4">
                                <label for="edit_fuel_type" class="form-label">Fuel Type</label>
                                <select class="form-select" id="edit_fuel_type" name="fuel_type">
                                    <option value="gasoline">Gasoline</option>
                                    <option value="diesel">Diesel</option>
                                </select>
                            </div>
                            
                            <div class="mb-4">
                                <label for="edit_description" class="form-label">Description</label>
                                <textarea class="form-control" id="edit_description" name="description" 
                                          rows="3" placeholder="Vehicle description (optional)"></textarea>
                            </div>
                            
                            <div class="form-check mb-4">
                                <input class="form-check-input" type="checkbox" role="switch" id="edit_is_active" name="is_active">
                                <label for="edit_is_active">Active</label>
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

        <script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>
        <?php
        /* Data island consumed by assets/js/vehicles.js. */
        $__ocp_data = [];
        /* The alert the actions file left behind. It is read once and removed: leaving it
         * in the session made the popup appear again on every later load of this page. */
        $__ocp_alert = isset($_SESSION['alert']) ? $_SESSION['alert'] : null;
        unset($_SESSION['alert']);
        if ($__ocp_alert !== null) {
            $__ocp_data["sESSIONAlertType"] = $__ocp_alert['type'] ?? '';
            $__ocp_data["sESSIONAlertTitle"] = $__ocp_alert['title'] ?? '';
            $__ocp_data["sESSIONAlertText"] = $__ocp_alert['text'] ?? '';
        }
        unset($__ocp_alert);
        /* Flags for the script. It is a separate request, so it cannot test this
         * page's variables itself; these answer for it. Each is false on an ordinary
         * load, so nothing is shown unless there is something to show. */
        $__ocp_data["hasMessage"] = isset($__ocp_data["sESSIONAlertType"]);
        $__ocp_data["showModal"] = ((string) ($show_modal ?: ''));
        ocp_page_data("vehicles", $__ocp_data);
        unset($__ocp_data);
        ?>
        <script src="<?php echo ocp_asset('assets/js/app.js'); ?>"></script>
        <script src="assets/js/vehicles.js.php"></script>
    </body>
</html>
