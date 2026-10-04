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
// The message the actions file wants shown. It is created here, before the
// actions run, so the markup below always finds it defined.
$message = '';
$message_type = ''; // success or danger

// All of this page's actions live in one file: deleting, editing and adding a
// spare part. The forms post back to this page, so it is pulled in before
// anything is read or rendered. A rejected submission leaves its message set and
// the markup below repopulates the form from $_POST.
if (!defined('OCP_SPARE_PARTS_ACTIONS_RAN')) {
    require __DIR__ . '/actions/spare_parts-actions.php';
}

// All of this page's fetching lives in one file: it returns the variables the
// markup below needs, which are unpacked into this scope. error_message is only
// taken when the endpoint actually set one, so a message from the actions file
// is not wiped out.
$ocp_endpoint = require __DIR__ . '/api/spare_parts-endpoint.php';
foreach ($ocp_endpoint as $ocp_key => $ocp_value) {
    if (in_array($ocp_key, ['error_message', 'message', 'message_type'], true) && empty($ocp_value)) {
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
        <title>Spare Parts - OCP Construction</title>
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
                            <h1 class="page-title">Spare Parts</h1>
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item active">Spare Parts</li>
                            </ol>
                        </div>
                        
                        <!-- Display all spare parts in a table -->
                        <div class="card mb-6">
                            <div class="card-header flex items-center justify-between">
                                <div>
                                    <i class="fas fa-table mr-1"></i>
                                    All Spare Parts & Materials
                                </div>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPartModal">
                                    <i class="fas fa-plus mr-1"></i> Add Spare Part
                                </button>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($parts)): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped table-hover" id="datatablesSimple">
                                        <thead>
                                            <tr>
                                                <th>Part Number</th>
                                                <th>Part Name</th>
                                                <th>Category</th>
                                                <th>Unit</th>
                                                <th>Min Stock Level</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($parts as $part): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($part['part_number']); ?></td>
                                                <td><?php echo htmlspecialchars($part['part_name']); ?></td>
                                                <td><?php echo htmlspecialchars($part['category_name']); ?></td>
                                                <td><?php echo htmlspecialchars($part['unit_of_measure']); ?></td>
                                                <td>
                                                    <?php 
                                                    $min_stock = isset($part['min_stock_level']) ? (int)$part['min_stock_level'] : 0;
                                                    echo ($min_stock == 0) ? '<span class="text-muted">Not Set</span>' : htmlspecialchars($min_stock);
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $min_stock = isset($part['min_stock_level']) ? (int)$part['min_stock_level'] : 0;
                                                    if ($min_stock <= 0): 
                                                    ?>
                                                        <span class="badge badge-danger">No Alert</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-success">Alert Enabled</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="inline-flex items-center gap-2" role="group">
                                                        <!-- View button - matches item_names.php style with rounded edges -->
                                                        <button type="button" class="btn btn-sm btn-outline-primary view-btn" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#viewPartModal"
                                                                data-id="<?php echo $part['id']; ?>"
                                                                data-part_number="<?php echo htmlspecialchars($part['part_number']); ?>"
                                                                data-part_name="<?php echo htmlspecialchars($part['part_name']); ?>"
                                                                data-category_name="<?php echo htmlspecialchars($part['category_name']); ?>"
                                                                data-unit_of_measure="<?php echo htmlspecialchars($part['unit_of_measure']); ?>"
                                                                data-min_stock_level="<?php echo htmlspecialchars($part['min_stock_level'] ?? '0'); ?>"
                                                                data-created_at="<?php echo htmlspecialchars($part['created_at'] ?? ''); ?>"
                                                                data-updated_at="<?php echo htmlspecialchars($part['updated_at'] ?? ''); ?>">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        
                                                        <!-- Edit button - matches item_names.php style with rounded edges -->
                                                        <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editPartModal" 
                                                                data-id="<?php echo $part['id']; ?>"
                                                                data-part_number="<?php echo htmlspecialchars($part['part_number']); ?>"
                                                                data-part_name="<?php echo htmlspecialchars($part['part_name']); ?>"
                                                                data-category_id="<?php echo $part['category_id']; ?>"
                                                                data-unit_of_measure="<?php echo htmlspecialchars($part['unit_of_measure']); ?>"
                                                                data-min_stock_level="<?php echo htmlspecialchars($part['min_stock_level'] ?? '0'); ?>">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        
                                                        <!-- Delete button - matches item_names.php style with rounded edges -->
                                                        <button type="button" class="btn btn-sm btn-danger delete-btn" 
                                                                data-id="<?php echo $part['id']; ?>"
                                                                data-part_number="<?php echo htmlspecialchars($part['part_number']); ?>"
                                                                data-part_name="<?php echo htmlspecialchars($part['part_name']); ?>">
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
                                <p class="text-center">No spare parts found. Add your first spare part using the button above.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </main>
                <?php include 'includes/footer.php';?>
            </div>
        </div>
        
        <!-- Add Part Modal -->
        <div class="modal" id="addPartModal" tabindex="-1" aria-labelledby="addPartModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addPartModalLabel">Add New Spare Part</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="" id="addPartForm">
                        <div class="modal-body">
                            <div class="form-floating mb-4">
                                <input type="text" class="form-control" id="part_number" name="part_number" 
                                        value="<?php echo isset($_POST['part_number']) ? htmlspecialchars($_POST['part_number']) : ''; ?>" 
                                        required maxlength="50" placeholder="Part Number">
                                <label for="part_number">Part Number <span class="text-danger">*</span></label>
                                <div class="form-text ml-1">Unique identifier for the item.</div>
                            </div>
                            <div class="form-floating mb-4">
                                <input type="text" class="form-control" id="part_name" name="part_name" 
                                        value="<?php echo isset($_POST['part_name']) ? htmlspecialchars($_POST['part_name']) : ''; ?>" 
                                        required maxlength="255" placeholder="Part Name">
                                <label for="part_name">Part Name <span class="text-danger">*</span></label>
                                <div class="form-text ml-1">Full descriptive name of the item.</div>
                            </div>
                            <div class="form-floating mb-4">
                                <select class="form-select" id="category_id" name="category_id" required>
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>" <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['category_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="category_id">Category <span class="text-danger">*</span></label>
                                <div class="form-text ml-1">Category or classification of the item.</div>
                            </div>
                            <div class="form-floating mb-4">
                                <select class="form-select" id="unit_of_measure" name="unit_of_measure">
                                    <option value="pcs" <?php echo (isset($_POST['unit_of_measure']) && $_POST['unit_of_measure'] == 'pcs') ? 'selected' : 'selected'; ?>>Pieces</option>
                                    <option value="set" <?php echo (isset($_POST['unit_of_measure']) && $_POST['unit_of_measure'] == 'set') ? 'selected' : ''; ?>>Set</option>
                                    <option value="box" <?php echo (isset($_POST['unit_of_measure']) && $_POST['unit_of_measure'] == 'box') ? 'selected' : ''; ?>>Box</option>
                                    <option value="kg" <?php echo (isset($_POST['unit_of_measure']) && $_POST['unit_of_measure'] == 'kg') ? 'selected' : ''; ?>>Kilogram</option>
                                    <option value="m" <?php echo (isset($_POST['unit_of_measure']) && $_POST['unit_of_measure'] == 'm') ? 'selected' : ''; ?>>Meter</option>
                                    <option value="liter" <?php echo (isset($_POST['unit_of_measure']) && $_POST['unit_of_measure'] == 'liter') ? 'selected' : ''; ?>>Liter(L)</option>
                                </select>
                                <label for="unit_of_measure">Unit of Measure</label>
                                <div class="form-text ml-1">Select the unit of measurement for this item.</div>
                            </div>
                            <div class="form-floating mb-4">
                                <input type="number" class="form-control" id="min_stock_level" name="min_stock_level" 
                                        value="<?php echo isset($_POST['min_stock_level']) ? htmlspecialchars($_POST['min_stock_level']) : '0'; ?>" 
                                        min="0" placeholder="Minimum Stock Level">
                                <label for="min_stock_level">Minimum Stock Level</label>
                                <div class="form-text ml-1">Set minimum stock level for low stock alerts. Set to 0 to disable alerts.</div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Add Part</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Edit Part Modal -->
        <div class="modal" id="editPartModal" tabindex="-1" aria-labelledby="editPartModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editPartModalLabel">Edit Spare Part</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="" id="editPartForm">
                        <input type="hidden" name="edit_id" id="edit_id">
                        <div class="modal-body">
                            <div class="form-floating mb-4">
                                <input type="text" class="form-control" id="edit_part_number" name="part_number" 
                                        required maxlength="50" placeholder="Part Number">
                                <label for="edit_part_number">Part Number <span class="text-danger">*</span></label>
                                <div class="form-text ml-1">Unique identifier for the item.</div>
                            </div>
                            <div class="form-floating mb-4">
                                <input type="text" class="form-control" id="edit_part_name" name="part_name" 
                                        required maxlength="255" placeholder="Part Name">
                                <label for="edit_part_name">Part Name <span class="text-danger">*</span></label>
                                <div class="form-text ml-1">Full descriptive name of the item.</div>
                            </div>
                            <div class="form-floating mb-4">
                                <select class="form-select" id="edit_category_id" name="category_id" required>
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>">
                                        <?php echo htmlspecialchars($category['category_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="edit_category_id">Category <span class="text-danger">*</span></label>
                                <div class="form-text ml-1">Category or classification of the item.</div>
                            </div>
                            <div class="form-floating mb-4">
                                <select class="form-select" id="edit_unit_of_measure" name="unit_of_measure">
                                    <option value="pcs">Pieces</option>
                                    <option value="set">Set</option>
                                    <option value="box">Box</option>
                                    <option value="kg">Kilogram</option>
                                    <option value="m">Meter</option>
                                    <option value="liter">Liter(L)</option>
                                </select>
                                <label for="edit_unit_of_measure">Unit of Measure</label>
                                <div class="form-text ml-1">Select the unit of measurement for this item.</div>
                            </div>
                            <div class="form-floating mb-4">
                                <input type="number" class="form-control" id="edit_min_stock_level" name="min_stock_level" 
                                        min="0" placeholder="Minimum Stock Level">
                                <label for="edit_min_stock_level">Minimum Stock Level</label>
                                <div class="form-text ml-1">Set minimum stock level for low stock alerts. Set to 0 to disable alerts.</div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Update Part</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- View Part Modal -->
        <div class="modal" id="viewPartModal" tabindex="-1" aria-labelledby="viewPartModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="viewPartModalLabel">Spare Part Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2 mb-4">
                            <div class="min-w-0">
                                <strong>Part Number:</strong>
                                <p id="view_part_number" class="text-muted"></p>
                            </div>
                            <div class="min-w-0">
                                <strong>Part Name:</strong>
                                <p id="view_part_name" class="text-muted"></p>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2 mb-4">
                            <div class="min-w-0">
                                <strong>Category:</strong>
                                <p id="view_category_name" class="text-muted"></p>
                            </div>
                            <div class="min-w-0">
                                <strong>Unit of Measure:</strong>
                                <p id="view_unit_of_measure" class="text-muted"></p>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2 mb-4">
                            <div class="min-w-0">
                                <strong>Minimum Stock Level:</strong>
                                <p id="view_min_stock_level" class="text-muted"></p>
                            </div>
                            <div class="min-w-0">
                                <strong>Status:</strong>
                                <p id="view_status" class="text-muted"></p>
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
        <form method="POST" action="" id="deleteForm">
            <input type="hidden" name="delete_id" id="delete_id">
        </form>

        <script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
        <?php
        /* Data island consumed by assets/js/spare_parts.js. */
        $__ocp_data = [];
        /* The alert the actions file left behind. It is read once and removed: leaving it
         * in the session made the popup appear again on every later load of this page. */
        $__ocp_alert = isset($_SESSION['alert']) ? $_SESSION['alert'] : null;
        unset($_SESSION['alert']);
        if ($__ocp_alert !== null) {
            $__ocp_data["sESSIONAlertType"] = $__ocp_alert['type'] ?? '';
            $__ocp_data["sESSIONAlertTitle"] = $__ocp_alert['title'] ?? '';
            $__ocp_data["sESSIONAlertMessage"] = $__ocp_alert['message'] ?? '';
        }
        unset($__ocp_alert);
        /* Flags for the script. It is a separate request, so it cannot test this
         * page's variables itself; these answer for it. Each is false on an ordinary
         * load, so nothing is shown unless there is something to show. */
        $__ocp_data["hasMessage"] = isset($__ocp_data["sESSIONAlertType"]);
        $__ocp_data["isSuccess"] = (($__ocp_data["sESSIONAlertType"] ?? '') === 'success');
        ocp_page_data("spare_parts", $__ocp_data);
        unset($__ocp_data);
        ?>
        <script src="<?php echo ocp_asset('assets/js/app.js'); ?>"></script>
        <script src="assets/js/spare_parts.js.php"></script>
    </body>
</html>
