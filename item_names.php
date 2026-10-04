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

// All of this page's actions live in one file. The forms post back to this page,
// so it is pulled in here, before anything is read or rendered. The guard keeps a
// re-render from running them twice.
if (!defined('OCP_ITEM_NAMES_ACTIONS_RAN')) {
    require __DIR__ . '/actions/item_names-actions.php';
}

// Check for session messages to display with SweetAlert2
$swal_message = '';
$swal_message_type = '';
if (isset($_SESSION['swal_message'])) {
    $swal_message = $_SESSION['swal_message'];
    $swal_message_type = $_SESSION['swal_message_type'];
    unset($_SESSION['swal_message']);
    unset($_SESSION['swal_message_type']);
}

// All of this page's fetching lives in one file: it returns the variables the
// markup below needs, which are unpacked into this scope.
$ocp_endpoint = require __DIR__ . '/api/item_names-endpoint.php';
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
        <title>Item Names - OCP Construction</title>
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
                            <h1 class="page-title">Item Names</h1>
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                <li class="breadcrumb-item active">Item Names</li>
                            </ol>
                        </div>
                        
                        <!-- Display all items in a table -->
                        <div class="card mb-6">
                            <div class="card-header flex justify-between items-center">
                                <div>
                                    <i class="fas fa-table mr-1"></i>
                                    All Items Name
                                </div>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addItemModal">
                                    <i class="fas fa-plus mr-1"></i> Add Item Name
                                </button>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($items)): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped table-hover" id="datatablesSimple">
                                        <thead>
                                            <tr>
                                                <th>Item Code</th>
                                                <th>Item Name</th>
                                                <th>Category</th>
                                                <th>Unit of Measure</th>
                                                <th>Min Stock Level</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($items as $item): 
                                                $min_stock = $item['min_stock_level'];
                                                $status_badge = $min_stock > 0 ? 
                                                    '<span class="badge badge-success">Alert Enabled</span>' : 
                                                    '<span class="badge badge-danger">No Alert</span>';
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($item['item_code']); ?></td>
                                                <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                                <td>
                                                    <?php 
                                                    if (!empty($item['category_name'])) {
                                                        echo htmlspecialchars($item['category_name']);
                                                    } else {
                                                        echo '<span class="text-muted">No Category</span>';
                                                    }
                                                    ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($item['unit_of_measure']); ?></td>
                                                <td><?php echo $min_stock > 0 ? $min_stock : '<span class="text-muted">Not Set</span>'; ?></td>
                                                <td><?php echo $status_badge; ?></td>
                                                <td>
                                                    <div class="inline-flex gap-2" role="group">
                                                        <form method="POST" action="" class="inline">
                                                            <input type="hidden" name="view_item_id" value="<?php echo $item['id']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-secondary">
                                                                <i class="fas fa-eye"></i>
                                                            </button>
                                                        </form>
                                                        <form method="POST" action="" class="inline">
                                                            <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editItemModal" 
                                                                    data-id="<?php echo $item['id']; ?>" 
                                                                    data-code="<?php echo htmlspecialchars($item['item_code']); ?>" 
                                                                    data-name="<?php echo htmlspecialchars($item['item_name']); ?>" 
                                                                    data-category="<?php echo $item['category_id']; ?>"
                                                                    data-unit="<?php echo htmlspecialchars($item['unit_of_measure']); ?>"
                                                                    data-min-stock="<?php echo $item['min_stock_level']; ?>">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                        </form>
                                                        <form method="POST" action="" class="inline">
                                                            <button type="button" class="btn btn-sm btn-danger delete-item-btn" 
                                                                    data-id="<?php echo $item['id']; ?>" 
                                                                    data-name="<?php echo htmlspecialchars($item['item_name']); ?>">
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
                                <p class="text-center">No items found. Add your first item using the button above.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </main>
                <?php include 'includes/footer.php';?>
            </div>
        </div>
        
        <!-- Add Item Modal -->
        <div class="modal fade" id="addItemModal" tabindex="-1" aria-labelledby="addItemModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addItemModalLabel">Add New Item</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="" id="addItemForm">
                        <input type="hidden" name="item_form_action" value="add">
                        <div class="modal-body">
                            <div class="form-floating mb-4">
                                <input type="text" class="form-control" id="item_code" name="item_code" 
                                       value="<?php echo isset($_POST['item_code']) ? htmlspecialchars($_POST['item_code']) : ''; ?>" 
                                       required maxlength="50" placeholder="Item Code">
                                <label for="item_code">Item Code <span class="text-danger">*</span></label>
                                <div class="form-text ml-1">Unique identifier for the item.</div>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <input type="text" class="form-control" id="item_name" name="item_name" 
                                       value="<?php echo isset($_POST['item_name']) ? htmlspecialchars($_POST['item_name']) : ''; ?>" 
                                       required maxlength="255" placeholder="Item Name">
                                <label for="item_name">Item Name <span class="text-danger">*</span></label>
                                <div class="form-text ml-1">Full descriptive name of the item.</div>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <select class="form-control" id="category_id" name="category_id" required>
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>" 
                                        <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['category_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="category_id">Item Category <span class="text-danger">*</span></label>
                                <div class="form-text ml-1">Select the category for this item.</div>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <select class="form-control" id="unit_of_measure" name="unit_of_measure" required>
                                    <option value="">Select Unit</option>
                                    <option value="pcs" <?php echo (isset($_POST['unit_of_measure']) && $_POST['unit_of_measure'] == 'pcs') ? 'selected' : ''; ?>>Pieces (pc)</option>
                                    <option value="box" <?php echo (isset($_POST['unit_of_measure']) && $_POST['unit_of_measure'] == 'box') ? 'selected' : ''; ?>>Box</option>
                                    <option value="pack" <?php echo (isset($_POST['unit_of_measure']) && $_POST['unit_of_measure'] == 'pack') ? 'selected' : ''; ?>>Pack</option>
                                    <option value="set" <?php echo (isset($_POST['unit_of_measure']) && $_POST['unit_of_measure'] == 'set') ? 'selected' : ''; ?>>Set</option>
                                    <option value="meter" <?php echo (isset($_POST['unit_of_measure']) && $_POST['unit_of_measure'] == 'meter') ? 'selected' : ''; ?>>Meter (m)</option>
                                    <option value="kilogram" <?php echo (isset($_POST['unit_of_measure']) && $_POST['unit_of_measure'] == 'kilogram') ? 'selected' : ''; ?>>Kilogram (kg)</option>
                                    <option value="liter" <?php echo (isset($_POST['unit_of_measure']) && $_POST['unit_of_measure'] == 'liter') ? 'selected' : ''; ?>>Liter (L)</option>
                                    <option value="gallon" <?php echo (isset($_POST['unit_of_measure']) && $_POST['unit_of_measure'] == 'gallon') ? 'selected' : ''; ?>>Gallon</option>
                                    <option value="roll" <?php echo (isset($_POST['unit_of_measure']) && $_POST['unit_of_measure'] == 'roll') ? 'selected' : ''; ?>>Roll</option>
                                    <option value="dozen" <?php echo (isset($_POST['unit_of_measure']) && $_POST['unit_of_measure'] == 'dozen') ? 'selected' : ''; ?>>Dozen</option>
                                    <option value="pair" <?php echo (isset($_POST['unit_of_measure']) && $_POST['unit_of_measure'] == 'pair') ? 'selected' : ''; ?>>Pair</option>
                                    <option value="bottle" <?php echo (isset($_POST['unit_of_measure']) && $_POST['unit_of_measure'] == 'bottle') ? 'selected' : ''; ?>>Bottle</option>
                                    <option value="can" <?php echo (isset($_POST['unit_of_measure']) && $_POST['unit_of_measure'] == 'can') ? 'selected' : ''; ?>>Can</option>
                                    <option value="carton" <?php echo (isset($_POST['unit_of_measure']) && $_POST['unit_of_measure'] == 'carton') ? 'selected' : ''; ?>>Carton</option>
                                    <option value="bag" <?php echo (isset($_POST['unit_of_measure']) && $_POST['unit_of_measure'] == 'bag') ? 'selected' : ''; ?>>Bag</option>
                                    <option value="sack" <?php echo (isset($_POST['unit_of_measure']) && $_POST['unit_of_measure'] == 'sack') ? 'selected' : ''; ?>>Sack</option>
                                    <option value="foot" <?php echo (isset($_POST['unit_of_measure']) && $_POST['unit_of_measure'] == 'foot') ? 'selected' : ''; ?>>Foot (ft)</option>
                                    <option value="inch" <?php echo (isset($_POST['unit_of_measure']) && $_POST['unit_of_measure'] == 'inch') ? 'selected' : ''; ?>>Inch (in)</option>
                                    <option value="sheet" <?php echo (isset($_POST['unit_of_measure']) && $_POST['unit_of_measure'] == 'sheet') ? 'selected' : ''; ?>>Sheet</option>
                                    <option value="board" <?php echo (isset($_POST['unit_of_measure']) && $_POST['unit_of_measure'] == 'board') ? 'selected' : ''; ?>>Board</option>
                                    <option value="length" <?php echo (isset($_POST['unit_of_measure']) && $_POST['unit_of_measure'] == 'length') ? 'selected' : ''; ?>>Length</option>
                                    <option value="other" <?php echo (isset($_POST['unit_of_measure']) && $_POST['unit_of_measure'] == 'other') ? 'selected' : ''; ?>>Other</option>
                                </select>
                                <label for="unit_of_measure">Unit of Measure <span class="text-danger">*</span></label>
                                <div class="form-text ml-1">Select the unit of measurement for this item.</div>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <input type="number" class="form-control" id="min_stock_level" name="min_stock_level" 
                                       value="<?php echo isset($_POST['min_stock_level']) ? htmlspecialchars($_POST['min_stock_level']) : '0'; ?>" 
                                       min="0" step="1" placeholder="Minimum Stock Level">
                                <label for="min_stock_level">Minimum Stock Level</label>
                                <div class="form-text ml-1">Set minimum stock level for low stock alerts. Set to 0 to disable alerts.</div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Add Item</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- View Item Modal -->
        <?php if ($view_item): 
            $min_stock = $view_item['min_stock_level'];
            $status_badge = $min_stock > 0 ? 
                '<span class="badge badge-success">Alert Enabled (Min: ' . $min_stock . ')</span>' : 
                '<span class="badge badge-danger">No Alert</span>';
            
            // Format created_at and updated_at
            $created_at = isset($view_item['created_at']) ? date('m-d-Y H:i:s', strtotime($view_item['created_at'])) : 'N/A';
            $updated_at = isset($view_item['updated_at']) ? date('m-d-Y H:i:s', strtotime($view_item['updated_at'])) : 'N/A';
        ?>
        <div class="modal fade" id="viewItemModal" tabindex="-1" aria-labelledby="viewItemModalLabel" aria-hidden="false">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="viewItemModalLabel">Item Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2 mb-4">
                            <div class="min-w-0">
                                <strong>Item Code:</strong>
                                <p class="text-muted"><?php echo htmlspecialchars($view_item['item_code']); ?></p>
                            </div>
                            <div class="min-w-0">
                                <strong>Item Name:</strong>
                                <p class="text-muted"><?php echo htmlspecialchars($view_item['item_name']); ?></p>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2 mb-4">
                            <div class="min-w-0">
                                <strong>Category:</strong>
                                <p class="text-muted">
                                    <?php 
                                    if (!empty($view_item['category_name'])) {
                                        echo htmlspecialchars($view_item['category_name']);
                                    } else {
                                        echo '<span class="text-muted">No Category</span>';
                                    }
                                    ?>
                                </p>
                            </div>
                            <div class="min-w-0">
                                <strong>Unit of Measure:</strong>
                                <p class="text-muted"><?php echo htmlspecialchars($view_item['unit_of_measure']); ?></p>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2 mb-4">
                            <div class="min-w-0">
                                <strong>Min Stock Level:</strong>
                                <p class="text-muted"><?php echo $min_stock > 0 ? $min_stock : 'Not Set'; ?></p>
                            </div>
                            <div class="min-w-0">
                                <strong>Status:</strong>
                                <p class="text-muted"><?php echo $status_badge; ?></p>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2 mb-4">
                            <div class="min-w-0">
                                <strong>Created At:</strong>
                                <p class="text-muted"><?php echo $created_at; ?></p>
                            </div>
                            <div class="min-w-0">
                                <strong>Updated At:</strong>
                                <p class="text-muted"><?php echo $updated_at; ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Edit Item Modal -->
        <div class="modal fade" id="editItemModal" tabindex="-1" aria-labelledby="editItemModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editItemModalLabel">Edit Item</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="actions/item_names-actions.php" id="editItemForm">
                        <!-- Named ocp_action, not action: a form control called "action" shadows
                             the form's own action property, so the script's fetch(this.action)
                             sent the input element instead of the URL and never reached here. -->
                        <input type="hidden" name="ocp_action" value="update">
                        <input type="hidden" name="id" id="edit_item_id">
                        <div class="modal-body">
                            <div class="form-floating mb-4">
                                <input type="text" class="form-control" id="edit_item_code" name="item_code" required maxlength="50" placeholder="Item Code">
                                <label for="edit_item_code">Item Code <span class="text-danger">*</span></label>
                                <div class="form-text ml-1">Unique identifier for the item.</div>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <input type="text" class="form-control" id="edit_item_name" name="item_name" required maxlength="255" placeholder="Item Name">
                                <label for="edit_item_name">Item Name <span class="text-danger">*</span></label>
                                <div class="form-text ml-1">Full descriptive name of the item.</div>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <select class="form-control" id="edit_category_id" name="category_id" required>
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>">
                                        <?php echo htmlspecialchars($category['category_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="edit_category_id">Item Category <span class="text-danger">*</span></label>
                                <div class="form-text ml-1">Select the category for this item.</div>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <select class="form-control" id="edit_unit_of_measure" name="unit_of_measure" required>
                                    <option value="">Select Unit</option>
                                    <option value="pcs">Pieces (pc)</option>
                                    <option value="box">Box</option>
                                    <option value="pack">Pack</option>
                                    <option value="set">Set</option>
                                    <option value="meter">Meter (m)</option>
                                    <option value="kilogram">Kilogram (kg)</option>
                                    <option value="liter">Liter (L)</option>
                                    <option value="gallon">Gallon</option>
                                    <option value="roll">Roll</option>
                                    <option value="dozen">Dozen</option>
                                    <option value="pair">Pair</option>
                                    <option value="bottle">Bottle</option>
                                    <option value="can">Can</option>
                                    <option value="carton">Carton</option>
                                    <option value="bag">Bag</option>
                                    <option value="sack">Sack</option>
                                    <option value="foot">Foot (ft)</option>
                                    <option value="inch">Inch (in)</option>
                                    <option value="sheet">Sheet</option>
                                    <option value="board">Board</option>
                                    <option value="length">Length</option>
                                    <option value="other">Other</option>
                                </select>
                                <label for="edit_unit_of_measure">Unit of Measure <span class="text-danger">*</span></label>
                                <div class="form-text ml-1">Select the unit of measurement for this item.</div>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <input type="number" class="form-control" id="edit_min_stock_level" name="min_stock_level" 
                                       min="0" step="1" placeholder="Minimum Stock Level">
                                <label for="edit_min_stock_level">Minimum Stock Level</label>
                                <div class="form-text ml-1">Set minimum stock level for low stock alerts. Set to 0 to disable alerts.</div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Update Item</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>
        <?php
        /* Data island consumed by assets/js/item_names.js. */
        $__ocp_data = [];
        /* swalMessageType = $swal_message_type [guarded] */
        if (!empty($swal_message)) {
            $__ocp_data["swalMessageType"] = $swal_message_type;
        }
        /* swalMessageType2 = $swal_message_type === "success" ? "Success" : "Error" [guarded] */
        if (!empty($swal_message)) {
            $__ocp_data["swalMessageType2"] = $swal_message_type === "success" ? "Success" : "Error";
        }
        /* swalMessage = $swal_message [guarded] */
        if (!empty($swal_message)) {
            $__ocp_data["swalMessage"] = $swal_message;
        }
        /* The script is a separate request, so it cannot test the page's variables or
         * $_SESSION itself. These flags tell it what to do: whether there is a message to
         * show, and whether a rejected post means the add form should be reopened. Both
         * are false on an ordinary load.
         *
         * They are derived from the variables above rather than from $_SESSION, because
         * the page has already consumed and unset the session's message by this point. */
        $__ocp_data["hasMessage"] = !empty($swal_message);
        $__ocp_data["reopenAddModal"] = ($_SERVER['REQUEST_METHOD'] === 'POST'
            && $swal_message_type === 'error');
        $__ocp_data["openViewModal"] = (bool) $view_item;
        ocp_page_data("item_names", $__ocp_data);
        unset($__ocp_data);
        ?>
        <script src="<?php echo ocp_asset('assets/js/app.js'); ?>"></script>
        <script src="assets/js/item_names.js.php"></script>
    </body>
</html>
