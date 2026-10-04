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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if it's a delete operation
    if (isset($_POST['delete_id'])) {
        $delete_id = $_POST['delete_id'];
        
        try {
            $deleteStmt = $pdo->prepare("DELETE FROM spare_parts WHERE id = :id");
            $deleteStmt->bindParam(':id', $delete_id);
            
            if ($deleteStmt->execute()) {
                $_SESSION['alert'] = [
                    'type' => 'success',
                    'title' => 'Success!',
                    'message' => 'Spare part deleted successfully!'
                ];
            } else {
                $_SESSION['alert'] = [
                    'type' => 'error',
                    'title' => 'Error!',
                    'message' => 'Error deleting spare part. Please try again.'
                ];
            }
        } catch(PDOException $e) {
            $_SESSION['alert'] = [
                'type' => 'error',
                'title' => 'Database Error!',
                'message' => 'Database error: ' . $e->getMessage()
            ];
        }
        header("Location: ".$_SERVER['PHP_SELF']);
        exit();
    } 
    // Check if it's an edit operation
    else if (isset($_POST['edit_id'])) {
        $edit_id = $_POST['edit_id'];
        $part_number = trim($_POST['part_number']);
        $part_name = trim($_POST['part_name']);
        $category_id = trim($_POST['category_id']);
        $unit_of_measure = trim($_POST['unit_of_measure']);
        $min_stock_level = isset($_POST['min_stock_level']) ? (int)$_POST['min_stock_level'] : 0;
        
        // Basic validation
        if (empty($part_number) || empty($part_name) || empty($category_id)) {
            $_SESSION['alert'] = [
                'type' => 'error',
                'title' => 'Validation Error!',
                'message' => 'Part number, part name, and category are required fields.'
            ];
            header("Location: ".$_SERVER['PHP_SELF']);
            exit();
        }
        
        // Validate min_stock_level is non-negative
        if ($min_stock_level < 0) {
            $_SESSION['alert'] = [
                'type' => 'error',
                'title' => 'Validation Error!',
                'message' => 'Minimum stock level cannot be negative.'
            ];
            header("Location: ".$_SERVER['PHP_SELF']);
            exit();
        }
        
        try {
            // Check if part number already exists (excluding current part)
            $checkStmt = $pdo->prepare("SELECT id FROM spare_parts WHERE part_number = :part_number AND id != :id");
            $checkStmt->bindParam(':part_number', $part_number);
            $checkStmt->bindParam(':id', $edit_id);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() > 0) {
                $_SESSION['alert'] = [
                    'type' => 'error',
                    'title' => 'Duplicate Entry!',
                    'message' => 'Part number already exists. Please use a different part number.'
                ];
                header("Location: ".$_SERVER['PHP_SELF']);
                exit();
            }
            
            // Update spare part
            $updateStmt = $pdo->prepare("UPDATE spare_parts SET part_number = :part_number, part_name = :part_name, category_id = :category_id, unit_of_measure = :unit_of_measure, min_stock_level = :min_stock_level WHERE id = :id");
            $updateStmt->bindParam(':part_number', $part_number);
            $updateStmt->bindParam(':part_name', $part_name);
            $updateStmt->bindParam(':category_id', $category_id);
            $updateStmt->bindParam(':unit_of_measure', $unit_of_measure);
            $updateStmt->bindParam(':min_stock_level', $min_stock_level);
            $updateStmt->bindParam(':id', $edit_id);
            
            if ($updateStmt->execute()) {
                $_SESSION['alert'] = [
                    'type' => 'success',
                    'title' => 'Success!',
                    'message' => 'Spare part updated successfully!'
                ];
            } else {
                $_SESSION['alert'] = [
                    'type' => 'error',
                    'title' => 'Error!',
                    'message' => 'Error updating spare part. Please try again.'
                ];
            }
        } catch(PDOException $e) {
            $_SESSION['alert'] = [
                'type' => 'error',
                'title' => 'Database Error!',
                'message' => 'Database error: ' . $e->getMessage()
            ];
        }
        header("Location: ".$_SERVER['PHP_SELF']);
        exit();
    }
    // It's an add operation
    else {
        $part_number = trim($_POST['part_number']);
        $part_name = trim($_POST['part_name']);
        $category_id = trim($_POST['category_id']);
        $unit_of_measure = trim($_POST['unit_of_measure']);
        $min_stock_level = isset($_POST['min_stock_level']) ? (int)$_POST['min_stock_level'] : 0;
        
        // Basic validation
        if (empty($part_number) || empty($part_name) || empty($category_id)) {
            $_SESSION['alert'] = [
                'type' => 'error',
                'title' => 'Validation Error!',
                'message' => 'Part number, part name, and category are required fields.'
            ];
            header("Location: ".$_SERVER['PHP_SELF']);
            exit();
        }
        
        // Validate min_stock_level is non-negative
        if ($min_stock_level < 0) {
            $_SESSION['alert'] = [
                'type' => 'error',
                'title' => 'Validation Error!',
                'message' => 'Minimum stock level cannot be negative.'
            ];
            header("Location: ".$_SERVER['PHP_SELF']);
            exit();
        }
        
        try {
            // Check if part number already exists
            $checkStmt = $pdo->prepare("SELECT id FROM spare_parts WHERE part_number = :part_number");
            $checkStmt->bindParam(':part_number', $part_number);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() > 0) {
                $_SESSION['alert'] = [
                    'type' => 'error',
                    'title' => 'Duplicate Entry!',
                    'message' => 'Part number already exists. Please use a different part number.'
                ];
                header("Location: ".$_SERVER['PHP_SELF']);
                exit();
            }
            
            // Insert new spare part
            $insertStmt = $pdo->prepare("INSERT INTO spare_parts (part_number, part_name, category_id, unit_of_measure, min_stock_level) VALUES (:part_number, :part_name, :category_id, :unit_of_measure, :min_stock_level)");
            $insertStmt->bindParam(':part_number', $part_number);
            $insertStmt->bindParam(':part_name', $part_name);
            $insertStmt->bindParam(':category_id', $category_id);
            $insertStmt->bindParam(':unit_of_measure', $unit_of_measure);
            $insertStmt->bindParam(':min_stock_level', $min_stock_level);
            
            if ($insertStmt->execute()) {
                $_SESSION['alert'] = [
                    'type' => 'success',
                    'title' => 'Success!',
                    'message' => 'Spare part added successfully!'
                ];
            } else {
                $_SESSION['alert'] = [
                    'type' => 'error',
                    'title' => 'Error!',
                    'message' => 'Error adding spare part. Please try again.'
                ];
            }
        } catch(PDOException $e) {
            $_SESSION['alert'] = [
                'type' => 'error',
                'title' => 'Database Error!',
                'message' => 'Database error: ' . $e->getMessage()
            ];
        }
        header("Location: ".$_SERVER['PHP_SELF']);
        exit();
    }
}

// Get all spare parts from the database with category names
try {
    $partsStmt = $pdo->prepare("
        SELECT sp.*, spc.category_name 
        FROM spare_parts sp 
        LEFT JOIN spare_parts_categories spc ON sp.category_id = spc.id 
        ORDER BY sp.created_at DESC
    ");
    $partsStmt->execute();
    $parts = $partsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $parts = [];
    $error_message = 'Error fetching spare parts: ' . $e->getMessage();
}

// Get all categories for dropdown
try {
    $categoriesStmt = $pdo->prepare("SELECT * FROM spare_parts_categories ORDER BY category_name");
    $categoriesStmt->execute();
    $categories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $categories = [];
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
        <title>Spare Parts - OCP Construction</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" />
        <link href="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/style.min.css" rel="stylesheet" />
        <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
        <link href="css/styles.css" rel="stylesheet" />
        <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
        <style>
            /* Match the action buttons styling from item_names.php with rounded edges */
            .btn-group .btn {
                margin-right: 7px;
                border-radius: 0.25rem !important; /* This ensures rounded corners */
            }
            .btn-group .btn:first-child {
                border-top-left-radius: 0.25rem !important;
                border-bottom-left-radius: 0.25rem !important;
            }
            .btn-group .btn:last-child {
                margin-right: 0;
                border-top-right-radius: 0.25rem !important;
                border-bottom-right-radius: 0.25rem !important;
            }
            /* Override Bootstrap's btn-group styling that might make buttons square */
            .btn-group > .btn:not(:first-child) {
                border-top-left-radius: 0.25rem !important;
                border-bottom-left-radius: 0.25rem !important;
            }
            .btn-group > .btn:not(:last-child) {
                border-top-right-radius: 0.25rem !important;
                border-bottom-right-radius: 0.25rem !important;
            }
            .btn-group .btn-info, 
            .btn-group .btn-warning, 
            .btn-group .btn-danger {
                border-radius: 0.25rem !important;
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
                        <h1 class="mt-4">Spare Parts</h1>
                        <ol class="breadcrumb mb-4">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Spare Parts</li>
                        </ol>
                        
                        <!-- Display all spare parts in a table -->
                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fas fa-table me-1"></i>
                                    All Spare Parts & Materials
                                </div>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPartModal">
                                    <i class="fas fa-plus me-1"></i> Add Spare Part
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
                                                        <span class="badge bg-danger">No Alert</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-success">Alert Enabled</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <!-- View button - matches item_names.php style with rounded edges -->
                                                        <button type="button" class="btn btn-sm btn-info view-btn" 
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
        <div class="modal fade" id="addPartModal" tabindex="-1" aria-labelledby="addPartModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-l">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addPartModalLabel">Add New Spare Part</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="" id="addPartForm">
                        <div class="modal-body">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="part_number" name="part_number" 
                                        value="<?php echo isset($_POST['part_number']) ? htmlspecialchars($_POST['part_number']) : ''; ?>" 
                                        required maxlength="50" placeholder="Part Number">
                                <label for="part_number">Part Number <span class="text-danger">*</span></label>
                                <div class="form-text ms-1">Unique identifier for the item.</div>
                            </div>
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="part_name" name="part_name" 
                                        value="<?php echo isset($_POST['part_name']) ? htmlspecialchars($_POST['part_name']) : ''; ?>" 
                                        required maxlength="255" placeholder="Part Name">
                                <label for="part_name">Part Name <span class="text-danger">*</span></label>
                                <div class="form-text ms-1">Full descriptive name of the item.</div>
                            </div>
                            <div class="form-floating mb-3">
                                <select class="form-select" id="category_id" name="category_id" required>
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>" <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['category_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="category_id">Category <span class="text-danger">*</span></label>
                                <div class="form-text ms-1">Category or classification of the item.</div>
                            </div>
                            <div class="form-floating mb-3">
                                <select class="form-select" id="unit_of_measure" name="unit_of_measure">
                                    <option value="pcs" <?php echo (isset($_POST['unit_of_measure']) && $_POST['unit_of_measure'] == 'pcs') ? 'selected' : 'selected'; ?>>Pieces</option>
                                    <option value="set" <?php echo (isset($_POST['unit_of_measure']) && $_POST['unit_of_measure'] == 'set') ? 'selected' : ''; ?>>Set</option>
                                    <option value="box" <?php echo (isset($_POST['unit_of_measure']) && $_POST['unit_of_measure'] == 'box') ? 'selected' : ''; ?>>Box</option>
                                    <option value="kg" <?php echo (isset($_POST['unit_of_measure']) && $_POST['unit_of_measure'] == 'kg') ? 'selected' : ''; ?>>Kilogram</option>
                                    <option value="m" <?php echo (isset($_POST['unit_of_measure']) && $_POST['unit_of_measure'] == 'm') ? 'selected' : ''; ?>>Meter</option>
                                    <option value="liter" <?php echo (isset($_POST['unit_of_measure']) && $_POST['unit_of_measure'] == 'liter') ? 'selected' : ''; ?>>Liter(L)</option>
                                </select>
                                <label for="unit_of_measure">Unit of Measure</label>
                                <div class="form-text ms-1">Select the unit of measurement for this item.</div>
                            </div>
                            <div class="form-floating mb-3">
                                <input type="number" class="form-control" id="min_stock_level" name="min_stock_level" 
                                        value="<?php echo isset($_POST['min_stock_level']) ? htmlspecialchars($_POST['min_stock_level']) : '0'; ?>" 
                                        min="0" placeholder="Minimum Stock Level">
                                <label for="min_stock_level">Minimum Stock Level</label>
                                <div class="form-text ms-1">Set minimum stock level for low stock alerts. Set to 0 to disable alerts.</div>
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
        <div class="modal fade" id="editPartModal" tabindex="-1" aria-labelledby="editPartModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-l">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editPartModalLabel">Edit Spare Part</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="" id="editPartForm">
                        <input type="hidden" name="edit_id" id="edit_id">
                        <div class="modal-body">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="edit_part_number" name="part_number" 
                                        required maxlength="50" placeholder="Part Number">
                                <label for="edit_part_number">Part Number <span class="text-danger">*</span></label>
                                <div class="form-text ms-1">Unique identifier for the item.</div>
                            </div>
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="edit_part_name" name="part_name" 
                                        required maxlength="255" placeholder="Part Name">
                                <label for="edit_part_name">Part Name <span class="text-danger">*</span></label>
                                <div class="form-text ms-1">Full descriptive name of the item.</div>
                            </div>
                            <div class="form-floating mb-3">
                                <select class="form-select" id="edit_category_id" name="category_id" required>
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>">
                                        <?php echo htmlspecialchars($category['category_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="edit_category_id">Category <span class="text-danger">*</span></label>
                                <div class="form-text ms-1">Category or classification of the item.</div>
                            </div>
                            <div class="form-floating mb-3">
                                <select class="form-select" id="edit_unit_of_measure" name="unit_of_measure">
                                    <option value="pcs">Pieces</option>
                                    <option value="set">Set</option>
                                    <option value="box">Box</option>
                                    <option value="kg">Kilogram</option>
                                    <option value="m">Meter</option>
                                    <option value="liter">Liter(L)</option>
                                </select>
                                <label for="edit_unit_of_measure">Unit of Measure</label>
                                <div class="form-text ms-1">Select the unit of measurement for this item.</div>
                            </div>
                            <div class="form-floating mb-3">
                                <input type="number" class="form-control" id="edit_min_stock_level" name="min_stock_level" 
                                        min="0" placeholder="Minimum Stock Level">
                                <label for="edit_min_stock_level">Minimum Stock Level</label>
                                <div class="form-text ms-1">Set minimum stock level for low stock alerts. Set to 0 to disable alerts.</div>
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
        <div class="modal fade" id="viewPartModal" tabindex="-1" aria-labelledby="viewPartModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="viewPartModalLabel">Spare Part Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Part Number:</strong>
                                <p id="view_part_number" class="text-muted"></p>
                            </div>
                            <div class="col-md-6">
                                <strong>Part Name:</strong>
                                <p id="view_part_name" class="text-muted"></p>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Category:</strong>
                                <p id="view_category_name" class="text-muted"></p>
                            </div>
                            <div class="col-md-6">
                                <strong>Unit of Measure:</strong>
                                <p id="view_unit_of_measure" class="text-muted"></p>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Minimum Stock Level:</strong>
                                <p id="view_min_stock_level" class="text-muted"></p>
                            </div>
                            <div class="col-md-6">
                                <strong>Status:</strong>
                                <p id="view_status" class="text-muted"></p>
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
        <form method="POST" action="" id="deleteForm">
            <input type="hidden" name="delete_id" id="delete_id">
        </form>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
        <script>
            // Simple sidebar toggle functionality
            document.addEventListener('DOMContentLoaded', function() {
                const sidebarToggle = document.getElementById('sidebarToggle');
                if (sidebarToggle) {
                    sidebarToggle.addEventListener('click', function() {
                        document.body.classList.toggle('sb-sidenav-toggled');
                    });
                }
                
                // Initialize DataTables
                const datatablesSimple = document.getElementById('datatablesSimple');
                if (datatablesSimple) {
                    new simpleDatatables.DataTable(datatablesSimple);
                }
                
                // Handle edit modal data
                const editModal = document.getElementById('editPartModal');
                if (editModal) {
                    editModal.addEventListener('show.bs.modal', function (event) {
                        const button = event.relatedTarget;
                        const id = button.getAttribute('data-id');
                        const part_number = button.getAttribute('data-part_number');
                        const part_name = button.getAttribute('data-part_name');
                        const category_id = button.getAttribute('data-category_id');
                        const unit_of_measure = button.getAttribute('data-unit_of_measure');
                        const min_stock_level = button.getAttribute('data-min_stock_level');
                        
                        document.getElementById('edit_id').value = id;
                        document.getElementById('edit_part_number').value = part_number;
                        document.getElementById('edit_part_name').value = part_name;
                        document.getElementById('edit_category_id').value = category_id;
                        document.getElementById('edit_unit_of_measure').value = unit_of_measure;
                        document.getElementById('edit_min_stock_level').value = min_stock_level;
                    });
                }
                
                // Handle view modal data with proper date formatting
                const viewModal = document.getElementById('viewPartModal');
                if (viewModal) {
                    viewModal.addEventListener('show.bs.modal', function (event) {
                        const button = event.relatedTarget;
                        const part_number = button.getAttribute('data-part_number');
                        const part_name = button.getAttribute('data-part_name');
                        const category_name = button.getAttribute('data-category_name');
                        const unit_of_measure = button.getAttribute('data-unit_of_measure');
                        const min_stock_level = button.getAttribute('data-min_stock_level');
                        const created_at = button.getAttribute('data-created_at');
                        const updated_at = button.getAttribute('data-updated_at');
                        
                        document.getElementById('view_part_number').textContent = part_number || 'N/A';
                        document.getElementById('view_part_name').textContent = part_name || 'N/A';
                        document.getElementById('view_category_name').textContent = category_name || 'N/A';
                        document.getElementById('view_unit_of_measure').textContent = unit_of_measure || 'N/A';
                        document.getElementById('view_min_stock_level').textContent = min_stock_level || '0';
                        
                        // Set status based on min stock level
                        const minStock = parseInt(min_stock_level) || 0;
                        const statusElement = document.getElementById('view_status');
                        if (minStock <= 0) {
                            statusElement.innerHTML = '<span class="badge bg-danger">No Alert</span>';
                        } else {
                            statusElement.innerHTML = '<span class="badge bg-success">Alert Enabled (Min: ' + minStock + ')</span>';
                        }
                        
                        // Format dates to mm-dd-yyyy hh:mm:ss
                        function formatDate(dateString) {
                            if (!dateString) return 'N/A';
                            const date = new Date(dateString);
                            if (isNaN(date.getTime())) return 'N/A';
                            
                            const month = String(date.getMonth() + 1).padStart(2, '0');
                            const day = String(date.getDate()).padStart(2, '0');
                            const year = date.getFullYear();
                            const hours = String(date.getHours()).padStart(2, '0');
                            const minutes = String(date.getMinutes()).padStart(2, '0');
                            const seconds = String(date.getSeconds()).padStart(2, '0');
                            
                            return `${month}-${day}-${year} ${hours}:${minutes}:${seconds}`;
                        }
                        
                        document.getElementById('view_created_at').textContent = formatDate(created_at);
                        document.getElementById('view_updated_at').textContent = formatDate(updated_at);
                    });
                }
                
                // Handle delete buttons
                const deleteButtons = document.querySelectorAll('.delete-btn');
                deleteButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        const id = this.getAttribute('data-id');
                        const part_number = this.getAttribute('data-part_number');
                        const part_name = this.getAttribute('data-part_name');
                        
                        Swal.fire({
                            title: 'Are you sure?',
                            text: `You are about to delete the spare part "${part_name}". This action cannot be undone.`,
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#d33',
                            cancelButtonColor: '#3085d6',
                            confirmButtonText: 'Yes, delete it!',
                            cancelButtonText: 'Cancel',
                            showLoaderOnConfirm: true,
                            preConfirm: () => {
                                return new Promise((resolve) => {
                                    document.getElementById('delete_id').value = id;
                                    document.getElementById('deleteForm').submit();
                                    // The page will reload after form submission
                                });
                            }
                        });
                    });
                });
                
                // Show SweetAlert notifications from session
                <?php if (isset($_SESSION['alert'])): ?>
                Swal.fire({
                    icon: '<?php echo $_SESSION['alert']['type']; ?>',
                    title: '<?php echo $_SESSION['alert']['title']; ?>',
                    text: '<?php echo $_SESSION['alert']['message']; ?>',
                    toast: false,
                    position: 'center',
                    showConfirmButton: true,
                    timer: null,
                    timerProgressBar: false
                });
                <?php unset($_SESSION['alert']); ?>
                <?php endif; ?>
                
                // Form validation with SweetAlert
                const addPartForm = document.getElementById('addPartForm');
                if (addPartForm) {
                    addPartForm.addEventListener('submit', function(e) {
                        const partNumber = document.getElementById('part_number').value.trim();
                        const partName = document.getElementById('part_name').value.trim();
                        const category = document.getElementById('category_id').value;
                        const minStock = document.getElementById('min_stock_level').value;
                        
                        if (!partNumber || !partName || !category) {
                            e.preventDefault();
                            Swal.fire({
                                icon: 'error',
                                title: 'Validation Error',
                                html: 'Please fill in all required fields:<br><br>' +
                                      '• Part Number<br>' +
                                      '• Part Name<br>' +
                                      '• Category',
                                confirmButtonText: 'OK'
                            });
                            return false;
                        }
                        
                        if (minStock && parseInt(minStock) < 0) {
                            e.preventDefault();
                            Swal.fire({
                                icon: 'error',
                                title: 'Validation Error',
                                text: 'Minimum stock level cannot be negative.',
                                confirmButtonText: 'OK'
                            });
                            return false;
                        }
                    });
                }
                
                const editPartForm = document.getElementById('editPartForm');
                if (editPartForm) {
                    editPartForm.addEventListener('submit', function(e) {
                        const partNumber = document.getElementById('edit_part_number').value.trim();
                        const partName = document.getElementById('edit_part_name').value.trim();
                        const category = document.getElementById('edit_category_id').value;
                        const minStock = document.getElementById('edit_min_stock_level').value;
                        
                        if (!partNumber || !partName || !category) {
                            e.preventDefault();
                            Swal.fire({
                                icon: 'error',
                                title: 'Validation Error',
                                html: 'Please fill in all required fields:<br><br>' +
                                      '• Part Number<br>' +
                                      '• Part Name<br>' +
                                      '• Category',
                                confirmButtonText: 'OK'
                            });
                            return false;
                        }
                        
                        if (minStock && parseInt(minStock) < 0) {
                            e.preventDefault();
                            Swal.fire({
                                icon: 'error',
                                title: 'Validation Error',
                                text: 'Minimum stock level cannot be negative.',
                                confirmButtonText: 'OK'
                            });
                            return false;
                        }
                    });
                }
                
                // Auto-close modals after successful form submission
                <?php if (isset($_SESSION['alert']) && $_SESSION['alert']['type'] === 'success'): ?>
                const addModal = bootstrap.Modal.getInstance(document.getElementById('addPartModal'));
                if (addModal) addModal.hide();
                
                const editModalInstance = bootstrap.Modal.getInstance(document.getElementById('editPartModal'));
                if (editModalInstance) editModalInstance.hide();
                <?php endif; ?>
            });
        </script>
    </body>
</html>