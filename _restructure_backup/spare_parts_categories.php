<?php
session_start();
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Database connection
require_once 'includes/db_config.php';

// Create spare_parts_categories table if it doesn't exist
$createTableSQL = "CREATE TABLE IF NOT EXISTS spare_parts_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

try {
    $pdo->exec($createTableSQL);
} catch(PDOException $e) {
    die("Error creating table: " . $e->getMessage());
}

// Process form submission
$message = '';
$message_type = ''; // success or danger

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if it's a delete operation
    if (isset($_POST['delete_id'])) {
        $delete_id = $_POST['delete_id'];
        
        try {
            $deleteStmt = $pdo->prepare("DELETE FROM spare_parts_categories WHERE id = :id");
            $deleteStmt->bindParam(':id', $delete_id);
            
            if ($deleteStmt->execute()) {
                $_SESSION['sweetalert'] = [
                    'title' => 'Success!',
                    'text' => 'Category deleted successfully!',
                    'icon' => 'success'
                ];
                header("Location: ".$_SERVER['PHP_SELF']);
                exit();
            } else {
                $_SESSION['sweetalert'] = [
                    'title' => 'Error!',
                    'text' => 'Error deleting category. Please try again.',
                    'icon' => 'error'
                ];
                header("Location: ".$_SERVER['PHP_SELF']);
                exit();
            }
        } catch(PDOException $e) {
            $_SESSION['sweetalert'] = [
                'title' => 'Database Error!',
                'text' => 'Database error: ' . $e->getMessage(),
                'icon' => 'error'
            ];
            header("Location: ".$_SERVER['PHP_SELF']);
            exit();
        }
    } 
    // Check if it's an edit operation
    else if (isset($_POST['edit_id'])) {
        $edit_id = $_POST['edit_id'];
        $category_name = trim($_POST['category_name']);
        $description = trim($_POST['description']);
        
        // Basic validation
        if (empty($category_name)) {
            $_SESSION['sweetalert'] = [
                'title' => 'Validation Error!',
                'text' => 'Category name is required.',
                'icon' => 'error'
            ];
            header("Location: ".$_SERVER['PHP_SELF']);
            exit();
        } else {
            try {
                // Check if category already exists (excluding current category)
                $checkStmt = $pdo->prepare("SELECT id FROM spare_parts_categories WHERE category_name = :category_name AND id != :id");
                $checkStmt->bindParam(':category_name', $category_name);
                $checkStmt->bindParam(':id', $edit_id);
                $checkStmt->execute();
                
                if ($checkStmt->rowCount() > 0) {
                    $_SESSION['sweetalert'] = [
                        'title' => 'Validation Error!',
                        'text' => 'Category name already exists. Please use a different name.',
                        'icon' => 'error'
                    ];
                    header("Location: ".$_SERVER['PHP_SELF']);
                    exit();
                } else {
                    // Update category
                    $updateStmt = $pdo->prepare("UPDATE spare_parts_categories SET category_name = :category_name, description = :description WHERE id = :id");
                    $updateStmt->bindParam(':category_name', $category_name);
                    $updateStmt->bindParam(':description', $description);
                    $updateStmt->bindParam(':id', $edit_id);
                    
                    if ($updateStmt->execute()) {
                        $_SESSION['sweetalert'] = [
                            'title' => 'Success!',
                            'text' => 'Category updated successfully!',
                            'icon' => 'success'
                        ];
                        header("Location: ".$_SERVER['PHP_SELF']);
                        exit();
                    } else {
                        $_SESSION['sweetalert'] = [
                            'title' => 'Error!',
                            'text' => 'Error updating category. Please try again.',
                            'icon' => 'error'
                        ];
                        header("Location: ".$_SERVER['PHP_SELF']);
                        exit();
                    }
                }
            } catch(PDOException $e) {
                $_SESSION['sweetalert'] = [
                    'title' => 'Database Error!',
                    'text' => 'Database error: ' . $e->getMessage(),
                    'icon' => 'error'
                ];
                header("Location: ".$_SERVER['PHP_SELF']);
                exit();
            }
        }
    }
    // It's an add operation
    else {
        $category_name = trim($_POST['category_name']);
        $description = trim($_POST['description']);
        
        // Basic validation
        if (empty($category_name)) {
            $_SESSION['sweetalert'] = [
                'title' => 'Validation Error!',
                'text' => 'Category name is required.',
                'icon' => 'error'
            ];
            header("Location: ".$_SERVER['PHP_SELF']);
            exit();
        } else {
            try {
                // Check if category already exists
                $checkStmt = $pdo->prepare("SELECT id FROM spare_parts_categories WHERE category_name = :category_name");
                $checkStmt->bindParam(':category_name', $category_name);
                $checkStmt->execute();
                
                if ($checkStmt->rowCount() > 0) {
                    $_SESSION['sweetalert'] = [
                        'title' => 'Validation Error!',
                        'text' => 'Category name already exists. Please use a different name.',
                        'icon' => 'error'
                    ];
                    header("Location: ".$_SERVER['PHP_SELF']);
                    exit();
                } else {
                    // Insert new category
                    $insertStmt = $pdo->prepare("INSERT INTO spare_parts_categories (category_name, description) VALUES (:category_name, :description)");
                    $insertStmt->bindParam(':category_name', $category_name);
                    $insertStmt->bindParam(':description', $description);
                    
                    if ($insertStmt->execute()) {
                        $_SESSION['sweetalert'] = [
                            'title' => 'Success!',
                            'text' => 'Category added successfully!',
                            'icon' => 'success'
                        ];
                        header("Location: ".$_SERVER['PHP_SELF']);
                        exit();
                    } else {
                        $_SESSION['sweetalert'] = [
                            'title' => 'Error!',
                            'text' => 'Error adding category. Please try again.',
                            'icon' => 'error'
                        ];
                        header("Location: ".$_SERVER['PHP_SELF']);
                        exit();
                    }
                }
            } catch(PDOException $e) {
                $_SESSION['sweetalert'] = [
                    'title' => 'Database Error!',
                    'text' => 'Database error: ' . $e->getMessage(),
                    'icon' => 'error'
                ];
                header("Location: ".$_SERVER['PHP_SELF']);
                exit();
            }
        }
    }
}

// Get all categories from the database
try {
    $categoriesStmt = $pdo->prepare("SELECT * FROM spare_parts_categories ORDER BY created_at DESC");
    $categoriesStmt->execute();
    $categories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $categories = [];
    $_SESSION['sweetalert'] = [
        'title' => 'Database Error!',
        'text' => 'Error fetching categories: ' . $e->getMessage(),
        'icon' => 'error'
    ];
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
        <title>Spare Parts Categories - OCP Construction</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" />
        <link href="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/style.min.css" rel="stylesheet" />
        <link href="css/styles.css" rel="stylesheet" />
        <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
        <!-- SweetAlert2 CSS -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
        <style>
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
                        <h1 class="mt-4">Spare Parts Categories</h1>
                        <ol class="breadcrumb mb-4">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Spare Parts Categories</li>
                        </ol>
                        
                        <!-- Display all categories in a table -->
                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fas fa-table me-1"></i>
                                    All Spare Parts Categories
                                </div>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                                    <i class="fas fa-plus me-1"></i> Add Category
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
                                                <td><?php echo htmlspecialchars($category['created_at']); ?></td>
                                                <td><?php echo htmlspecialchars($category['updated_at']); ?></td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <form method="POST" class="d-inline">
                                                            <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editCategoryModal" 
                                                                data-id="<?php echo $category['id']; ?>"
                                                                data-name="<?php echo htmlspecialchars($category['category_name']); ?>"
                                                                data-description="<?php echo htmlspecialchars($category['description']); ?>">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                        </form>
                                                        <form method="POST" class="d-inline">
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
        <div class="modal fade" id="addCategoryModal" tabindex="-1" aria-labelledby="addCategoryModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addCategoryModalLabel">Add New Category</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="" id="addCategoryForm">
                        <div class="modal-body">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="category_name" name="category_name" 
                                       value="<?php echo isset($_POST['category_name']) ? htmlspecialchars($_POST['category_name']) : ''; ?>" 
                                       required maxlength="100" placeholder="Category Name">
                                <label for="category_name">Category Name <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-3">
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
        <div class="modal fade" id="editCategoryModal" tabindex="-1" aria-labelledby="editCategoryModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editCategoryModalLabel">Edit Category</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="" id="editCategoryForm">
                        <input type="hidden" name="edit_id" id="edit_id">
                        <div class="modal-body">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="edit_category_name" name="category_name" 
                                       required maxlength="100" placeholder="Category Name">
                                <label for="edit_category_name">Category Name <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-3">
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

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>
        <!-- SweetAlert2 JS -->
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
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
                
                // Handle edit modal data
                const editModal = document.getElementById('editCategoryModal');
                if (editModal) {
                    editModal.addEventListener('show.bs.modal', function (event) {
                        const button = event.relatedTarget;
                        const id = button.getAttribute('data-id');
                        const name = button.getAttribute('data-name');
                        const description = button.getAttribute('data-description');
                        
                        document.getElementById('edit_id').value = id;
                        document.getElementById('edit_category_name').value = name;
                        document.getElementById('edit_description').value = description;
                    });
                }
                
                // Handle delete button clicks with SweetAlert2
                const deleteButtons = document.querySelectorAll('.delete-btn');
                deleteButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        const id = this.getAttribute('data-id');
                        const name = this.getAttribute('data-name');
                        
                        Swal.fire({
                            title: 'Are you sure?',
                            text: `You are about to delete the category "${name}". This action cannot be undone.`,
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#d33',
                            cancelButtonColor: '#3085d6',
                            confirmButtonText: 'Yes, delete it!',
                            cancelButtonText: 'Cancel'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                document.getElementById('delete_id').value = id;
                                document.getElementById('deleteForm').submit();
                            }
                        });
                    });
                });
                
                // Show SweetAlert2 notifications if any
                <?php if (isset($_SESSION['sweetalert'])): ?>
                    Swal.fire({
                        title: '<?php echo $_SESSION['sweetalert']['title']; ?>',
                        text: '<?php echo $_SESSION['sweetalert']['text']; ?>',
                        icon: '<?php echo $_SESSION['sweetalert']['icon']; ?>',
                        confirmButtonText: 'OK'
                    });
                    <?php unset($_SESSION['sweetalert']); ?>
                <?php endif; ?>
                
                // Form validation with SweetAlert2
                const addForm = document.getElementById('addCategoryForm');
                const editForm = document.getElementById('editCategoryForm');
                
                if (addForm) {
                    addForm.addEventListener('submit', function(e) {
                        const categoryName = document.getElementById('category_name').value.trim();
                        
                        if (!categoryName) {
                            e.preventDefault();
                            Swal.fire({
                                title: 'Validation Error!',
                                text: 'Category name is required.',
                                icon: 'error',
                                confirmButtonText: 'OK'
                            });
                        }
                    });
                }
                
                if (editForm) {
                    editForm.addEventListener('submit', function(e) {
                        const categoryName = document.getElementById('edit_category_name').value.trim();
                        
                        if (!categoryName) {
                            e.preventDefault();
                            Swal.fire({
                                title: 'Validation Error!',
                                text: 'Category name is required.',
                                icon: 'error',
                                confirmButtonText: 'OK'
                            });
                        }
                    });
                }
            });
        </script>
    </body>
</html>