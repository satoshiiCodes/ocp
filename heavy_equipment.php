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
// All of this page's actions live in one file: deleting, editing and adding a
// piece of equipment. The forms post back to this page, so it is pulled in before
// anything is read or rendered.
if (!defined('OCP_HEAVY_EQUIPMENT_ACTIONS_RAN')) {
    require __DIR__ . '/actions/heavy_equipment-actions.php';
}

// All of this page's fetching lives in one file: it returns the variables the
// markup below needs, which are unpacked into this scope. show_edit_modal comes
// back set when an edit was asked for, so the modal reopens filled in.
$ocp_endpoint = require __DIR__ . '/api/heavy_equipment-endpoint.php';
foreach ($ocp_endpoint as $ocp_key => $ocp_value) {
    ${$ocp_key} = $ocp_value;
}
unset($ocp_endpoint, $ocp_key, $ocp_value);

// Get user details
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT firstname, middlename, lastname, suffix FROM users WHERE id = :id");
$stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
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

// Check for alert in session
if (isset($_SESSION['alert'])) {
    $alert = $_SESSION['alert'];
    unset($_SESSION['alert']);
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>Heavy Equipment Vehicles- OCP Construction</title>
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
                            <h1 class="page-title">Heavy Equipment Vehicles</h1>
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                <li class="breadcrumb-item active">Heavy Equipment Vehicles</li>
                            </ol>
                        </div>
                        
                        <!-- Display all equipment in a table -->
                        <div class="card mb-6">
                            <div class="card-header flex justify-between items-center">
                                <div>
                                    <i class="fas fa-tools mr-1"></i>
                                    All Heavy Equipment Vehicles
                                </div>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEquipmentModal">
                                    <i class="fas fa-plus mr-1"></i> Add Heavy Equipment Vehicle
                                </button>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($equipment)): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped table-hover" id="datatablesSimple">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Heavy Vehicle Name</th>
                                                <th>Fuel Type</th>
                                                <th>Description</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($equipment as $item): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($item['id']); ?></td>
                                                <td><?php echo htmlspecialchars($item['equipment_name']); ?></td>
                                                <td>
                                                    <span class="badge badge-neutral">
                                                        <?php echo htmlspecialchars(ucfirst($item['fuel_type'])); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php 
                                                    if (!empty($item['description'])) {
                                                        echo htmlspecialchars($item['description']);
                                                    } else {
                                                        echo '<span class="text-muted">No description</span>';
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php if ($item['is_active']): ?>
                                                        <span class="badge badge-success">Active</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-danger">Inactive</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="inline-flex gap-2" role="group">
                                                        <!-- View Button with POST form -->
                                                        <form method="POST" class="inline" action="view_heavy_equipment.php" style="display: inline;">
                                                            <input type="hidden" name="equipment_id" value="<?php echo $item['id']; ?>">
                                                            <button type="submit" class="btn btn-sm bg-info-600 text-white">
                                                                <i class="fas fa-eye"></i>
                                                            </button>
                                                        </form>
                                                        
                                                        <!-- Edit Button with POST form -->
                                                        <form method="POST" class="inline" action="" style="display: inline;">
                                                            <input type="hidden" name="edit_request_id" value="<?php echo $item['id']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-warning">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                        </form>
                                                        
                                                        <!-- Delete Button -->
                                                        <button type="button" class="btn btn-sm btn-danger delete-btn" 
                                                                data-id="<?php echo $item['id']; ?>" 
                                                                data-name="<?php echo htmlspecialchars($item['equipment_name']); ?>">
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
                                <p class="text-center">No equipment found. Add your first equipment using the button above.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </main>
                <?php include 'includes/footer.php';?>
            </div>
        </div>
        
        <!-- Add Equipment Modal -->
        <div class="modal fade" id="addEquipmentModal" tabindex="-1" aria-labelledby="addEquipmentModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addEquipmentModalLabel">Add New Equipment</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="" id="addEquipmentForm">
                        <div class="modal-body">
                            <div class="form-floating mb-4">
                                <input type="text" class="form-control" id="equipment_name" name="equipment_name" 
                                       placeholder="Equipment Name" required maxlength="100">
                                <label for="equipment_name">Equipment Name <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="mb-4">
                                <label for="fuel_type" class="form-label">Fuel Type</label>
                                <select class="form-select" id="fuel_type" name="fuel_type">
                                    <option value="gasoline">Gasoline</option>
                                    <option value="diesel">Diesel</option>
                                </select>
                            </div>
                            
                            <div class="mb-4">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" 
                                          rows="3" placeholder="Equipment description"></textarea>
                            </div>
                            
                            <div class="form-check mb-4">
                                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" checked>
                                <label for="is_active">Active</label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Add Equipment</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Edit Equipment Modal -->
        <?php if ($show_edit_modal && isset($edit_equipment)): ?>
        <div class="modal fade" id="editEquipmentModal" tabindex="-1" aria-labelledby="editEquipmentModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editEquipmentModalLabel">Edit Equipment</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="" id="editEquipmentForm">
                        <input type="hidden" name="edit_id" id="edit_id" value="<?php echo isset($edit_equipment['id']) ? $edit_equipment['id'] : ''; ?>">
                        <div class="modal-body">
                            <div class="form-floating mb-4">
                                <input type="text" class="form-control" id="edit_equipment_name" name="equipment_name" 
                                       placeholder="Equipment Name" 
                                       value="<?php echo isset($edit_equipment['equipment_name']) ? htmlspecialchars($edit_equipment['equipment_name']) : ''; ?>" 
                                       required maxlength="100">
                                <label for="edit_equipment_name">Equipment Name <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="mb-4">
                                <label for="edit_fuel_type" class="form-label">Fuel Type</label>
                                <select class="form-select" id="edit_fuel_type" name="fuel_type">
                                    <option value="gasoline" <?php echo (isset($edit_equipment['fuel_type']) && $edit_equipment['fuel_type'] == 'gasoline') ? 'selected' : ''; ?>>Gasoline</option>
                                    <option value="diesel" <?php echo (isset($edit_equipment['fuel_type']) && $edit_equipment['fuel_type'] == 'diesel') ? 'selected' : ''; ?>>Diesel</option>
                                </select>
                            </div>
                            
                            <div class="mb-4">
                                <label for="edit_description" class="form-label">Description</label>
                                <textarea class="form-control" id="edit_description" name="description" 
                                          rows="3" placeholder="Equipment description"><?php echo isset($edit_equipment['description']) ? htmlspecialchars($edit_equipment['description']) : ''; ?></textarea>
                            </div>
                            
                            <div class="form-check mb-4">
                                <input class="form-check-input" type="checkbox" id="edit_is_active" name="is_active" 
                                    <?php echo (isset($edit_equipment['is_active']) && $edit_equipment['is_active']) ? 'checked' : ''; ?> value="1">
                                <label for="edit_is_active">Active</label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Update Equipment</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Hidden form for delete operations -->
        <form method="POST" action="" id="deleteForm" style="display: none;">
            <input type="hidden" name="delete_id" id="delete_id" value="">
        </form>

        <script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>
        <?php
        /* Data island consumed by assets/js/heavy_equipment.js. */
        $__ocp_data = [];
        /* alertType = $alert['type'] [guarded] */
        if (isset($alert)) {
            $__ocp_data["alertType"] = $alert['type'];
        }
        /* alertTitle = $alert['title'] [guarded] */
        if (isset($alert)) {
            $__ocp_data["alertTitle"] = $alert['title'];
        }
        /* alertMessage = $alert['message'] [guarded] */
        if (isset($alert)) {
            $__ocp_data["alertMessage"] = $alert['message'];
        }
        /* Whether the edit modal has to open by itself, with the record filled in. */
        $__ocp_data["showEditModal"] = ($show_edit_modal && isset($edit_equipment));
        /* Flags for the script. It is a separate request, so it cannot test this
         * page's variables itself; these answer for it. Each is false on an ordinary
         * load, so nothing is shown unless there is something to show. */
        $__ocp_data["hasAlert"] = (!empty($alert));
        ocp_page_data("heavy_equipment", $__ocp_data);
        unset($__ocp_data);
        ?>
        <script src="<?php echo ocp_asset('assets/js/app.js'); ?>"></script>
        <script src="assets/js/heavy_equipment.js.php"></script>
    </body>
</html>
