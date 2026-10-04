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
// All of this page's actions live in one file. The page's forms post back here,
// so it is pulled in before anything is read or rendered.
// The message the actions file leaves behind. It is read here, on every load, because a
// handler that stores it and redirects is answered by a fresh GET - and it is unset so it
// is shown once.
$sweetalert = [];
if (isset($_SESSION['sweetalert'])) {
    $sweetalert = $_SESSION['sweetalert'];
    unset($_SESSION['sweetalert']);
}

if (!defined('OCP_SPARE_PARTS_CATEGORIES_ACTIONS_RAN')) {
    require __DIR__ . '/actions/spare_parts_categories-actions.php';
}

// All of this page's fetching lives in one file: it returns the variables the
// markup below needs, which are unpacked into this scope.
$ocp_endpoint = require __DIR__ . '/api/spare_parts_categories-endpoint.php';
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
        <title>Spare Parts Categories - OCP Construction</title>
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
                            <h1 class="page-title">Spare Parts Categories</h1>
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item active">Spare Parts Categories</li>
                            </ol>
                        </div>
                        
                        <!-- Display all categories in a table -->
                        <div class="card mb-6">
                            <div class="card-header flex items-center justify-between">
                                <div>
                                    <i class="fas fa-table mr-1"></i>
                                    All Spare Parts Categories
                                </div>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                                    <i class="fas fa-plus mr-1"></i> Add Category
                                </button>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($categories)): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped table-hover" id="datatablesSimple">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Category Name</th>
                                                <th>Description</th>
                                                <th>Created At</th>
                                                <th>Updated At</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($categories as $category): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($category['id']); ?></td>
                                                <td><?php echo htmlspecialchars($category['category_name']); ?></td>
                                                <td><?php echo htmlspecialchars($category['description']); ?></td>
                                                <td><?php echo htmlspecialchars(ocp_datetime_mdy($category['created_at'])); ?></td>
                                                <td><?php echo htmlspecialchars(ocp_datetime_mdy($category['updated_at'])); ?></td>
                                                <td>
                                                    <div class="inline-flex items-center gap-2" role="group">
                                                        <form method="POST" class="inline">
                                                            <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editCategoryModal" 
                                                                data-id="<?php echo $category['id']; ?>"
                                                                data-name="<?php echo htmlspecialchars($category['category_name']); ?>"
                                                                data-description="<?php echo htmlspecialchars($category['description']); ?>">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                        </form>
                                                        <form method="POST" class="inline">
                                                            <button type="button" class="btn btn-sm btn-danger delete-btn" 
                                                                data-id="<?php echo $category['id']; ?>"
                                                                data-name="<?php echo htmlspecialchars($category['category_name']); ?>">
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
                                <p class="text-center">No categories found. Add your first category using the button above.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </main>
                <?php include 'includes/footer.php';?>
            </div>
        </div>
        
        <!-- Add Category Modal -->
        <div class="modal" id="addCategoryModal" tabindex="-1" aria-labelledby="addCategoryModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addCategoryModalLabel">Add New Category</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="" id="addCategoryForm">
                        <div class="modal-body">
                            <div class="form-floating mb-4">
                                <input type="text" class="form-control" id="category_name" name="category_name" 
                                       value="<?php echo isset($_POST['category_name']) ? htmlspecialchars($_POST['category_name']) : ''; ?>" 
                                       required maxlength="100" placeholder="Category Name">
                                <label for="category_name">Category Name <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <textarea class="form-control" id="description" name="description" 
                                          style="height: 100px" placeholder="Description"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                                <label for="description">Description</label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Add Category</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Edit Category Modal -->
        <div class="modal" id="editCategoryModal" tabindex="-1" aria-labelledby="editCategoryModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editCategoryModalLabel">Edit Category</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="" id="editCategoryForm">
                        <input type="hidden" name="edit_id" id="edit_id">
                        <div class="modal-body">
                            <div class="form-floating mb-4">
                                <input type="text" class="form-control" id="edit_category_name" name="category_name" 
                                       required maxlength="100" placeholder="Category Name">
                                <label for="edit_category_name">Category Name <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <textarea class="form-control" id="edit_description" name="description" 
                                          style="height: 100px" placeholder="Description"></textarea>
                                <label for="edit_description">Description</label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Update Category</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Delete Form (Hidden) -->
        <form method="POST" action="" id="deleteForm">
            <input type="hidden" name="delete_id" id="delete_id">
        </form>

        <script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>
        <!-- SweetAlert2 JS -->
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
        <?php
        /* Data island consumed by assets/js/spare_parts_categories.js. */
        $__ocp_data = [];
        /* Read from $sweetalert, not $_SESSION: the page has already consumed and
         * unset the session copy by this point. */
        if (!empty($sweetalert)) {
            $__ocp_data["sESSIONSweetalertTitle"] = $sweetalert['title'] ?? '';
        }
        if (!empty($sweetalert)) {
            $__ocp_data["sESSIONSweetalertText"] = $sweetalert['text'] ?? '';
        }
        if (!empty($sweetalert)) {
            $__ocp_data["sESSIONSweetalertIcon"] = $sweetalert['icon'] ?? '';
        }
        /* Flags for the script. It is a separate request, so it cannot test this
         * page's variables itself; these answer for it. Each is false on an ordinary
         * load, so nothing is shown unless there is something to show. */
        $__ocp_data["hasMessage"] = (!empty($sweetalert));
        ocp_page_data("spare_parts_categories", $__ocp_data);
        unset($__ocp_data);
        ?>
        <script src="<?php echo ocp_asset('assets/js/app.js'); ?>"></script>
        <script src="assets/js/spare_parts_categories.js.php"></script>
    </body>
</html>
