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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if this is a view request
    if (isset($_POST['view_expense_id'])) {
        // This is handled in the modal display section
    } else {
        // This is the form submission for adding expense types
        $expense_name = trim($_POST['expense_name']);
        $description = trim($_POST['description']);
        
        // Basic validation
        if (empty($expense_name)) {
            $_SESSION['swal_message'] = 'Expense name is required.';
            $_SESSION['swal_message_type'] = 'error';
        } else {
            try {
                // Check if expense name already exists
                $checkStmt = $pdo->prepare("SELECT id FROM expenses_type WHERE expense_name = :expense_name");
                $checkStmt->bindParam(':expense_name', $expense_name);
                $checkStmt->execute();
                
                if ($checkStmt->rowCount() > 0) {
                    $_SESSION['swal_message'] = 'Expense name already exists. Please use a different name.';
                    $_SESSION['swal_message_type'] = 'error';
                } else {
                    // Insert new expense type
                    $insertStmt = $pdo->prepare("INSERT INTO expenses_type (expense_name, description) VALUES (:expense_name, :description)");
                    $insertStmt->bindParam(':expense_name', $expense_name);
                    $insertStmt->bindParam(':description', $description);
                    
                    if ($insertStmt->execute()) {
                        $_SESSION['swal_message'] = 'Expense type added successfully!';
                        $_SESSION['swal_message_type'] = 'success';
                        
                        // Clear form fields
                        $_POST = array();
                        
                        // Refresh the page to show the new expense type
                        header("Location: ".$_SERVER['PHP_SELF']);
                        exit();
                    } else {
                        $_SESSION['swal_message'] = 'Error adding expense type. Please try again.';
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

// Get all expense types from the database
try {
    $expensesStmt = $pdo->prepare("SELECT * FROM expenses_type ORDER BY created_at DESC");
    $expensesStmt->execute();
    $expenses = $expensesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $expenses = [];
    $_SESSION['swal_message'] = 'Error fetching expense types: ' . $e->getMessage();
    $_SESSION['swal_message_type'] = 'error';
}

// Get expense details for view modal if requested
$view_expense = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['view_expense_id'])) {
    $view_expense_id = (int)$_POST['view_expense_id'];
    try {
        $viewStmt = $pdo->prepare("SELECT * FROM expenses_type WHERE id = :id");
        $viewStmt->bindParam(':id', $view_expense_id);
        $viewStmt->execute();
        $view_expense = $viewStmt->fetch(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        $_SESSION['swal_message'] = 'Error fetching expense details: ' . $e->getMessage();
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
        <title>Expense Types - OCP Construction</title>
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
                        <h1 class="mt-4">Expense Types</h1>
                        <ol class="breadcrumb mb-4">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Expense Types</li>
                        </ol>
                        
                        <!-- Display all expense types in a table -->
                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fas fa-table me-1"></i>
                                    All Expense Types
                                </div>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
                                    <i class="fas fa-plus me-1"></i> Add Expense Type
                                </button>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($expenses)): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped table-hover" id="datatablesSimple">
                                        <thead>
                                            <tr>
                                                <th>Expense Name</th>
                                                <th>Description</th>
                                                <th>Date Created</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($expenses as $expense): 
                                                $created_at = isset($expense['created_at']) ? date('m-d-Y', strtotime($expense['created_at'])) : 'N/A';
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($expense['expense_name']); ?></td>
                                                <td>
                                                    <?php 
                                                    if (!empty($expense['description'])) {
                                                        echo htmlspecialchars($expense['description']);
                                                    } else {
                                                        echo '<span class="text-muted fst-italic">No description</span>';
                                                    }
                                                    ?>
                                                </td>
                                                <td><?php echo $created_at; ?></td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <form method="POST" action="" class="d-inline">
                                                            <input type="hidden" name="view_expense_id" value="<?php echo $expense['id']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-info">
                                                                <i class="fas fa-eye"></i>
                                                            </button>
                                                        </form>
                                                        <form method="POST" action="" class="d-inline">
                                                            <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editExpenseModal" 
                                                                    data-id="<?php echo $expense['id']; ?>" 
                                                                    data-name="<?php echo htmlspecialchars($expense['expense_name']); ?>" 
                                                                    data-description="<?php echo htmlspecialchars($expense['description']); ?>">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                        </form>
                                                        <form method="POST" action="" class="d-inline">
                                                            <button type="button" class="btn btn-sm btn-danger delete-expense-btn" 
                                                                    data-id="<?php echo $expense['id']; ?>" 
                                                                    data-name="<?php echo htmlspecialchars($expense['expense_name']); ?>">
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
                                <p class="text-center">No expense types found. Add your first expense type using the button above.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </main>
                <?php include 'includes/footer.php';?>
            </div>
        </div>
        
        <!-- Add Expense Modal -->
        <div class="modal fade" id="addExpenseModal" tabindex="-1" aria-labelledby="addExpenseModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addExpenseModalLabel">Add New Expense Type</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="" id="addExpenseForm">
                        <div class="modal-body">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="expense_name" name="expense_name" 
                                       value="<?php echo isset($_POST['expense_name']) ? htmlspecialchars($_POST['expense_name']) : ''; ?>" 
                                       required maxlength="100" placeholder="Expense Name"
                                       oninput="capitalizeFirstLetter(this)">
                                <label for="expense_name">Expense Name <span class="text-danger">*</span></label>
                                <div class="form-text ms-1">Name of the expense type (e.g., Utilities, Rent, Supplies).</div>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <textarea class="form-control" id="description" name="description" 
                                          placeholder="Description" style="height: 100px; resize: vertical;"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                                <label for="description">Description</label>
                                <div class="form-text ms-1">Optional description of the expense type.</div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Add Expense Type</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- View Expense Modal -->
        <?php if ($view_expense): 
            $created_at = isset($view_expense['created_at']) ? date('m-d-Y H:i:s', strtotime($view_expense['created_at'])) : 'N/A';
            $updated_at = isset($view_expense['updated_at']) ? date('m-d-Y H:i:s', strtotime($view_expense['updated_at'])) : 'N/A';
        ?>
        <div class="modal fade" id="viewExpenseModal" tabindex="-1" aria-labelledby="viewExpenseModalLabel" aria-hidden="false">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="viewExpenseModalLabel">Expense Type Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <strong>Expense Name:</strong>
                            <p class="text-muted"><?php echo htmlspecialchars($view_expense['expense_name']); ?></p>
                        </div>
                        <div class="mb-3">
                            <strong>Description:</strong>
                            <p class="text-muted">
                                <?php 
                                if (!empty($view_expense['description'])) {
                                    echo nl2br(htmlspecialchars($view_expense['description']));
                                } else {
                                    echo '<span class="text-muted fst-italic">No description provided</span>';
                                }
                                ?>
                            </p>
                        </div>
                        <div class="row">
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

        <!-- Edit Expense Modal -->
        <div class="modal fade" id="editExpenseModal" tabindex="-1" aria-labelledby="editExpenseModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editExpenseModalLabel">Edit Expense Type</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="action/update_expense_type.php" id="editExpenseForm">
                        <input type="hidden" name="id" id="edit_expense_id">
                        <div class="modal-body">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="edit_expense_name" name="expense_name" required maxlength="100" placeholder="Expense Name"
                                       oninput="capitalizeFirstLetter(this)">
                                <label for="edit_expense_name">Expense Name <span class="text-danger">*</span></label>
                                <div class="form-text ms-1">Name of the expense type.</div>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <textarea class="form-control" id="edit_description" name="description" 
                                          placeholder="Description" style="height: 100px; resize: vertical;"></textarea>
                                <label for="edit_description">Description</label>
                                <div class="form-text ms-1">Optional description of the expense type.</div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Update Expense Type</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>
        <script>
            // Function to capitalize the first letter of each word
            function capitalizeFirstLetter(input) {
                let value = input.value;
                // Split the string into words
                let words = value.split(' ');
                
                // Capitalize the first letter of each word
                for (let i = 0; i < words.length; i++) {
                    if (words[i].length > 0) {
                        words[i] = words[i].charAt(0).toUpperCase() + words[i].slice(1).toLowerCase();
                    }
                }
                
                // Join the words back together
                input.value = words.join(' ');
            }

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
                    var addExpenseModal = new bootstrap.Modal(document.getElementById('addExpenseModal'));
                    addExpenseModal.show();
                <?php endif; ?>
                
                // Show view modal if expense details were requested
                <?php if ($view_expense): ?>
                    var viewExpenseModal = new bootstrap.Modal(document.getElementById('viewExpenseModal'));
                    viewExpenseModal.show();
                <?php endif; ?>
                
                // Edit modal handler
                const editExpenseModal = document.getElementById('editExpenseModal');
                if (editExpenseModal) {
                    editExpenseModal.addEventListener('show.bs.modal', function (event) {
                        const button = event.relatedTarget;
                        const id = button.getAttribute('data-id');
                        const name = button.getAttribute('data-name');
                        const description = button.getAttribute('data-description');
                        
                        document.getElementById('edit_expense_id').value = id;
                        document.getElementById('edit_expense_name').value = name;
                        document.getElementById('edit_description').value = description;
                    });
                }
                
                // Delete expense handler
                const deleteButtons = document.querySelectorAll('.delete-expense-btn');
                deleteButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        const id = this.getAttribute('data-id');
                        const name = this.getAttribute('data-name');
                        
                        Swal.fire({
                            title: 'Are you sure?',
                            text: `You are about to delete the expense type: ${name}`,
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#d33',
                            cancelButtonColor: '#3085d6',
                            confirmButtonText: 'Yes, delete it!'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                // Send AJAX request to delete the expense type
                                const formData = new FormData();
                                formData.append('id', id);
                                
                                fetch('action/delete_expense_type.php', {
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
                                        'An error occurred while deleting the expense type.',
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
                const editExpenseForm = document.getElementById('editExpenseForm');
                if (editExpenseForm) {
                    editExpenseForm.addEventListener('submit', function(e) {
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
                                text: 'An error occurred while updating the expense type.'
                            });
                        });
                    });
                }

                // Apply capitalization to any pre-filled values in the add form
                const expenseNameInput = document.getElementById('expense_name');
                if (expenseNameInput && expenseNameInput.value) {
                    capitalizeFirstLetter(expenseNameInput);
                }
            });
        </script>
    </body>
</html>