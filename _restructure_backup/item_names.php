<?php
session_start();
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Database connection
require_once 'includes/db_config.php';

// Check for session messages to display with SweetAlert2
$swal_message = '';
$swal_message_type = '';
if (isset($_SESSION['swal_message'])) {
    $swal_message = $_SESSION['swal_message'];
    $swal_message_type = $_SESSION['swal_message_type'];
    unset($_SESSION['swal_message']);
    unset($_SESSION['swal_message_type']);
}

// Process form submission
$message = '';
$message_type = ''; // success or danger

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if this is a view request
    if (isset($_POST['view_item_id'])) {
        // This is handled in the modal display section
    } else {
        // This is the original form submission for adding items
        $item_code = trim($_POST['item_code']);
        $item_name = trim($_POST['item_name']);
        $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $unit_of_measure = trim($_POST['unit_of_measure']);
        $min_stock_level = isset($_POST['min_stock_level']) ? (int)$_POST['min_stock_level'] : 0;
        
        // Basic validation
        if (empty($item_code) || empty($item_name) || empty($category_id) || empty($unit_of_measure)) {
            $_SESSION['swal_message'] = 'All fields are required.';
            $_SESSION['swal_message_type'] = 'error';
        } else if ($min_stock_level < 0) {
            $_SESSION['swal_message'] = 'Minimum stock level cannot be negative.';
            $_SESSION['swal_message_type'] = 'error';
        } else {
            try {
                // Check if item code already exists
                $checkStmt = $pdo->prepare("SELECT id FROM item_names WHERE item_code = :item_code");
                $checkStmt->bindParam(':item_code', $item_code);
                $checkStmt->execute();
                
                if ($checkStmt->rowCount() > 0) {
                    $_SESSION['swal_message'] = 'Item code already exists. Please use a different code.';
                    $_SESSION['swal_message_type'] = 'error';
                } else {
                    // Insert new item with category_id only (no item_type)
                    $insertStmt = $pdo->prepare("INSERT INTO item_names (item_code, item_name, category_id, unit_of_measure, min_stock_level) VALUES (:item_code, :item_name, :category_id, :unit_of_measure, :min_stock_level)");
                    $insertStmt->bindParam(':item_code', $item_code);
                    $insertStmt->bindParam(':item_name', $item_name);
                    $insertStmt->bindParam(':category_id', $category_id);
                    $insertStmt->bindParam(':unit_of_measure', $unit_of_measure);
                    $insertStmt->bindParam(':min_stock_level', $min_stock_level);
                    
                    if ($insertStmt->execute()) {
                        $_SESSION['swal_message'] = 'Item added successfully!';
                        $_SESSION['swal_message_type'] = 'success';
                        
                        // Clear form fields
                        $_POST = array();
                        
                        // Refresh the page to show the new item
                        header("Location: ".$_SERVER['PHP_SELF']);
                        exit();
                    } else {
                        $_SESSION['swal_message'] = 'Error adding item. Please try again.';
                        $_SESSION['swal_message_type'] = 'error';
                    }
                }
            } catch(PDOException $e) {
                $_SESSION['swal_message'] = 'Database error: ' . $e->getMessage();
                $_SESSION['swal_message_type'] = 'error';
            }
        }
    }
}

// Get all items from the database with category information
try {
    $itemsStmt = $pdo->prepare("
        SELECT i.*, c.category_name, c.id as category_id 
        FROM item_names i 
        LEFT JOIN items_categories c ON i.category_id = c.id 
        ORDER BY i.created_at DESC
    ");
    $itemsStmt->execute();
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $items = [];
    $_SESSION['swal_message'] = 'Error fetching items: ' . $e->getMessage();
    $_SESSION['swal_message_type'] = 'error';
}

// Get all categories for dropdown
try {
    $categoriesStmt = $pdo->prepare("SELECT * FROM items_categories ORDER BY category_name ASC");
    $categoriesStmt->execute();
    $categories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $categories = [];
    $_SESSION['swal_message'] = 'Error fetching categories: ' . $e->getMessage();
    $_SESSION['swal_message_type'] = 'error';
}

// Get item details for view modal if requested
$view_item = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['view_item_id'])) {
    $view_item_id = (int)$_POST['view_item_id'];
    try {
        $viewStmt = $pdo->prepare("
            SELECT i.*, c.category_name 
            FROM item_names i 
            LEFT JOIN items_categories c ON i.category_id = c.id 
            WHERE i.id = :id
        ");
        $viewStmt->bindParam(':id', $view_item_id);
        $viewStmt->execute();
        $view_item = $viewStmt->fetch(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        $_SESSION['swal_message'] = 'Error fetching item details: ' . $e->getMessage();
        $_SESSION['swal_message_type'] = 'error';
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
        <title>Item Names - OCP Construction</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" />
        <link href="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/style.min.css" rel="stylesheet" />
        <link href="css/styles.css" rel="stylesheet" />
        <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <style>
            .form-floating > .form-control:not(:placeholder-shown) ~ label::after {
                background-color: transparent !important;
            }
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
                        <h1 class="mt-4">Item Names</h1>
                        <ol class="breadcrumb mb-4">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Item Names</li>
                        </ol>
                        
                        <!-- Display all items in a table -->
                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fas fa-table me-1"></i>
                                    All Items Name
                                </div>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addItemModal">
                                    <i class="fas fa-plus me-1"></i> Add Item Name
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
                                                    '<span class="badge bg-success">Alert Enabled</span>' : 
                                                    '<span class="badge bg-danger">No Alert</span>';
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
                                                    <div class="btn-group" role="group">
                                                        <form method="POST" action="" class="d-inline">
                                                            <input type="hidden" name="view_item_id" value="<?php echo $item['id']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-info">
                                                                <i class="fas fa-eye"></i>
                                                            </button>
                                                        </form>
                                                        <form method="POST" action="" class="d-inline">
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
                                                        <form method="POST" action="" class="d-inline">
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
                        <div class="modal-body">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="item_code" name="item_code" 
                                       value="<?php echo isset($_POST['item_code']) ? htmlspecialchars($_POST['item_code']) : ''; ?>" 
                                       required maxlength="50" placeholder="Item Code">
                                <label for="item_code">Item Code <span class="text-danger">*</span></label>
                                <div class="form-text ms-1">Unique identifier for the item.</div>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="item_name" name="item_name" 
                                       value="<?php echo isset($_POST['item_name']) ? htmlspecialchars($_POST['item_name']) : ''; ?>" 
                                       required maxlength="255" placeholder="Item Name">
                                <label for="item_name">Item Name <span class="text-danger">*</span></label>
                                <div class="form-text ms-1">Full descriptive name of the item.</div>
                            </div>
                            
                            <div class="form-floating mb-3">
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
                                <div class="form-text ms-1">Select the category for this item.</div>
                            </div>
                            
                            <div class="form-floating mb-3">
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
                                <div class="form-text ms-1">Select the unit of measurement for this item.</div>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <input type="number" class="form-control" id="min_stock_level" name="min_stock_level" 
                                       value="<?php echo isset($_POST['min_stock_level']) ? htmlspecialchars($_POST['min_stock_level']) : '0'; ?>" 
                                       min="0" step="1" placeholder="Minimum Stock Level">
                                <label for="min_stock_level">Minimum Stock Level</label>
                                <div class="form-text ms-1">Set minimum stock level for low stock alerts. Set to 0 to disable alerts.</div>
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
                '<span class="badge bg-success">Alert Enabled (Min: ' . $min_stock . ')</span>' : 
                '<span class="badge bg-danger">No Alert</span>';
            
            // Format created_at and updated_at
            $created_at = isset($view_item['created_at']) ? date('m-d-Y H:i:s', strtotime($view_item['created_at'])) : 'N/A';
            $updated_at = isset($view_item['updated_at']) ? date('m-d-Y H:i:s', strtotime($view_item['updated_at'])) : 'N/A';
        ?>
        <div class="modal fade" id="viewItemModal" tabindex="-1" aria-labelledby="viewItemModalLabel" aria-hidden="false">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="viewItemModalLabel">Item Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Item Code:</strong>
                                <p class="text-muted"><?php echo htmlspecialchars($view_item['item_code']); ?></p>
                            </div>
                            <div class="col-md-6">
                                <strong>Item Name:</strong>
                                <p class="text-muted"><?php echo htmlspecialchars($view_item['item_name']); ?></p>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
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
                            <div class="col-md-6">
                                <strong>Unit of Measure:</strong>
                                <p class="text-muted"><?php echo htmlspecialchars($view_item['unit_of_measure']); ?></p>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Min Stock Level:</strong>
                                <p class="text-muted"><?php echo $min_stock > 0 ? $min_stock : 'Not Set'; ?></p>
                            </div>
                            <div class="col-md-6">
                                <strong>Status:</strong>
                                <p class="text-muted"><?php echo $status_badge; ?></p>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Created At:</strong>
                                <p class="text-muted"><?php echo $created_at; ?></p>
                            </div>
                            <div class="col-md-6">
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
                    <form method="POST" action="action/update_item.php" id="editItemForm">
                        <input type="hidden" name="id" id="edit_item_id">
                        <div class="modal-body">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="edit_item_code" name="item_code" required maxlength="50" placeholder="Item Code">
                                <label for="edit_item_code">Item Code <span class="text-danger">*</span></label>
                                <div class="form-text ms-1">Unique identifier for the item.</div>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="edit_item_name" name="item_name" required maxlength="255" placeholder="Item Name">
                                <label for="edit_item_name">Item Name <span class="text-danger">*</span></label>
                                <div class="form-text ms-1">Full descriptive name of the item.</div>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <select class="form-control" id="edit_category_id" name="category_id" required>
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>">
                                        <?php echo htmlspecialchars($category['category_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="edit_category_id">Item Category <span class="text-danger">*</span></label>
                                <div class="form-text ms-1">Select the category for this item.</div>
                            </div>
                            
                            <div class="form-floating mb-3">
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
                                <div class="form-text ms-1">Select the unit of measurement for this item.</div>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <input type="number" class="form-control" id="edit_min_stock_level" name="min_stock_level" 
                                       min="0" step="1" placeholder="Minimum Stock Level">
                                <label for="edit_min_stock_level">Minimum Stock Level</label>
                                <div class="form-text ms-1">Set minimum stock level for low stock alerts. Set to 0 to disable alerts.</div>
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

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>
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
                
                // Show modal if there was an error with form submission
                <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['swal_message_type']) && $_SESSION['swal_message_type'] === 'error'): ?>
                    var addItemModal = new bootstrap.Modal(document.getElementById('addItemModal'));
                    addItemModal.show();
                <?php endif; ?>
                
                // Show view modal if item details were requested
                <?php if ($view_item): ?>
                    var viewItemModal = new bootstrap.Modal(document.getElementById('viewItemModal'));
                    viewItemModal.show();
                <?php endif; ?>
                
                // Edit modal handler
                const editItemModal = document.getElementById('editItemModal');
                if (editItemModal) {
                    editItemModal.addEventListener('show.bs.modal', function (event) {
                        const button = event.relatedTarget;
                        const id = button.getAttribute('data-id');
                        const code = button.getAttribute('data-code');
                        const name = button.getAttribute('data-name');
                        const category = button.getAttribute('data-category');
                        const unit = button.getAttribute('data-unit');
                        const minStock = button.getAttribute('data-min-stock');
                        
                        document.getElementById('edit_item_id').value = id;
                        document.getElementById('edit_item_code').value = code;
                        document.getElementById('edit_item_name').value = name;
                        document.getElementById('edit_category_id').value = category;
                        document.getElementById('edit_unit_of_measure').value = unit;
                        document.getElementById('edit_min_stock_level').value = minStock;
                    });
                }
                
                // Delete item handler
                const deleteButtons = document.querySelectorAll('.delete-item-btn');
                deleteButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        const id = this.getAttribute('data-id');
                        const name = this.getAttribute('data-name');
                        
                        Swal.fire({
                            title: 'Are you sure?',
                            text: `You are about to delete the item: ${name}`,
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#d33',
                            cancelButtonColor: '#3085d6',
                            confirmButtonText: 'Yes, delete it!'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                // Send AJAX request to delete the item
                                const formData = new FormData();
                                formData.append('id', id);
                                
                                fetch('action/delete_item.php', {
                                    method: 'POST',
                                    body: formData
                                })
                                .then(response => response.json())
                                .then(data => {
                                    if (data.success) {
                                        Swal.fire(
                                            'Deleted!',
                                            data.message,
                                            'success'
                                        ).then(() => {
                                            location.reload();
                                        });
                                    } else {
                                        Swal.fire(
                                            'Error!',
                                            data.message,
                                            'error'
                                        );
                                    }
                                })
                                .catch(error => {
                                    Swal.fire(
                                        'Error!',
                                        'An error occurred while deleting the item.',
                                        'error'
                                    );
                                });
                            }
                        });
                    });
                });
                
                // Show SweetAlert2 messages from session
                <?php if (!empty($swal_message)): ?>
                    Swal.fire({
                        icon: '<?php echo $swal_message_type; ?>',
                        title: '<?php echo $swal_message_type === "success" ? "Success" : "Error"; ?>',
                        text: '<?php echo $swal_message; ?>',
                        timer: 3000,
                        showConfirmButton: false
                    });
                <?php endif; ?>
                
                // AJAX form submission for edit form
                const editItemForm = document.getElementById('editItemForm');
                if (editItemForm) {
                    editItemForm.addEventListener('submit', function(e) {
                        e.preventDefault();
                        
                        const formData = new FormData(this);
                        
                        fetch(this.action, {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Success!',
                                    text: data.message,
                                    timer: 2000,
                                    showConfirmButton: false
                                }).then(() => {
                                    location.reload();
                                });
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error!',
                                    text: data.message
                                });
                            }
                        })
                        .catch(error => {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error!',
                                text: 'An error occurred while updating the item.'
                            });
                        });
                    });
                }
            });
        </script>
    </body>
</html>