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
// re-render (which includes this page from the actions file) from running them
// twice.
if (!defined('OCP_EXPENSES_TYPE_ACTIONS_RAN')) {
    require __DIR__ . '/actions/expenses_type-actions.php';
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
// markup below needs, which are unpacked into this scope. A re-render reuses the
// data the actions file already gathered.
$ocp_endpoint = require __DIR__ . '/api/expenses_type-endpoint.php';
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
        <title>Expense Types - OCP Construction</title>
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
                            <h1 class="page-title">Expense Types</h1>
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                <li class="breadcrumb-item active">Expense Types</li>
                            </ol>
                        </div>
                        
                        <!-- Display all expense types in a table -->
                        <div class="card mb-6">
                            <div class="card-header flex items-center justify-between">
                                <div>
                                    <i class="fas fa-table mr-1"></i>
                                    All Expense Types
                                </div>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
                                    <i class="fas fa-plus mr-1"></i> Add Expense Type
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
                                                /* Hand the helper the raw value. Formatting here first
                                                   meant it received mm-dd-yyyy and re-read that as
                                                   dd-mm-yyyy, moving the day and the month. */
                                                $created_at = $expense['created_at'] ?? null;
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($expense['expense_name']); ?></td>
                                                <td>
                                                    <?php 
                                                    if (!empty($expense['description'])) {
                                                        echo htmlspecialchars($expense['description']);
                                                    } else {
                                                        echo '<span class="text-muted italic">No description</span>';
                                                    }
                                                    ?>
                                                </td>
                                                <td><?php echo htmlspecialchars(ocp_datetime_mdy($created_at)); ?></td>
                                                <td>
                                                    <div class="inline-flex items-center gap-1" role="group">
                                                        <form method="POST" action="" class="inline">
                                                            <input type="hidden" name="view_expense_id" value="<?php echo $expense['id']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-primary">
                                                                <i class="fas fa-eye"></i>
                                                            </button>
                                                        </form>
                                                        <form method="POST" action="" class="inline">
                                                            <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editExpenseModal" 
                                                                    data-id="<?php echo $expense['id']; ?>" 
                                                                    data-name="<?php echo htmlspecialchars($expense['expense_name']); ?>" 
                                                                    data-description="<?php echo htmlspecialchars($expense['description']); ?>"
                                                                    data-approval-required="<?php echo (int) ($expense['approval_required'] ?? 0); ?>">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                        </form>
                                                        <form method="POST" action="" class="inline">
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
        <div class="modal" id="addExpenseModal" tabindex="-1" aria-labelledby="addExpenseModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addExpenseModalLabel">Add New Expense Type</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="" id="addExpenseForm">
                        <input type="hidden" name="expense_form_action" value="add">
                        <div class="modal-body">
                            <div class="form-floating mb-4">
                                <input type="text" class="form-control" id="expense_name" name="expense_name" 
                                       value="<?php echo isset($_POST['expense_name']) ? htmlspecialchars($_POST['expense_name']) : ''; ?>" 
                                       required maxlength="100" placeholder="Expense Name"
                                       oninput="capitalizeFirstLetter(this)">
                                <label for="expense_name">Expense Name <span class="text-danger">*</span></label>
                                <div class="form-text ml-1">Name of the expense type (e.g., Utilities, Rent, Supplies).</div>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <textarea class="form-control" id="description" name="description" 
                                          placeholder="Description" style="height: 100px; resize: vertical;"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                                <label for="description">Description</label>
                                <div class="form-text ml-1">Optional description of the expense type.</div>
                            </div>

                            <!-- CEO approval. The hidden input sits first so that value is what
                                 posts when neither radio is chosen: preventing an unanswered
                                 question from silently clearing the setting on an edit. -->
                            <div class="mb-4">
                                <label class="form-label block">CEO approval required</label>
                                <input type="hidden" name="approval_required" id="approval_required_hidden" value="0">
                                <div class="flex flex-wrap items-center gap-5 mt-1">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="approval_required" id="add_approval_yes" value="1"
                                               onchange="document.getElementById('approval_required_hidden').value = this.value">
                                        <label class="form-check-label" for="add_approval_yes">Yes</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="approval_required" id="add_approval_no" value="0" checked
                                               onchange="document.getElementById('approval_required_hidden').value = this.value">
                                        <label class="form-check-label" for="add_approval_no">No</label>
                                    </div>
                                </div>
                                <div class="form-text ml-1">When set to Yes, a CEO signature is required before this expense can be recorded.</div>
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
            // Raw values; ocp_datetime_mdy() formats them for display below.
            $created_at = $view_expense['created_at'] ?? null;
            $updated_at = $view_expense['updated_at'] ?? null;
        ?>
        <div class="modal" id="viewExpenseModal" tabindex="-1" aria-labelledby="viewExpenseModalLabel" aria-hidden="false">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="viewExpenseModalLabel">Expense Type Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-4">
                            <strong>Expense Name:</strong>
                            <p class="text-muted"><?php echo htmlspecialchars($view_expense['expense_name']); ?></p>
                        </div>
                        <div class="mb-4">
                            <strong>Description:</strong>
                            <p class="text-muted">
                                <?php 
                                if (!empty($view_expense['description'])) {
                                    echo nl2br(htmlspecialchars($view_expense['description']));
                                } else {
                                    echo '<span class="text-muted italic">No description provided</span>';
                                }
                                ?>
                            </p>
                        </div>
                        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                            <div class="min-w-0">
                                <strong>Created At:</strong>
                                <p class="text-muted"><?php echo htmlspecialchars(ocp_datetime_mdy($created_at)); ?></p>
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

        <!-- Edit Expense Modal -->
        <div class="modal" id="editExpenseModal" tabindex="-1" aria-labelledby="editExpenseModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editExpenseModalLabel">Edit Expense Type</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="actions/expenses_type-actions.php" id="editExpenseForm">
                        <!-- Named ocp_action, not action: a form control called "action" shadows
                             the form's own action property, so the script's fetch(this.action)
                             sent the input element instead of the URL and never reached here. -->
                        <input type="hidden" name="ocp_action" value="update">
                        <input type="hidden" name="id" id="edit_expense_id">
                        <div class="modal-body">
                            <div class="form-floating mb-4">
                                <input type="text" class="form-control" id="edit_expense_name" name="expense_name" required maxlength="100" placeholder="Expense Name"
                                       oninput="capitalizeFirstLetter(this)">
                                <label for="edit_expense_name">Expense Name <span class="text-danger">*</span></label>
                                <div class="form-text ml-1">Name of the expense type.</div>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <textarea class="form-control" id="edit_description" name="description" 
                                          placeholder="Description" style="height: 100px; resize: vertical;"></textarea>
                                <label for="edit_description">Description</label>
                                <div class="form-text ml-1">Optional description of the expense type.</div>
                            </div>

                            <!-- CEO approval. The hidden input carries the value the form posts;
                                 the radios update it. The script sets the checked radio and this
                                 hidden value together when the dialog opens. -->
                            <div class="mb-4">
                                <label class="form-label block">CEO approval required</label>
                                <input type="hidden" name="approval_required" id="edit_approval_required" value="0">
                                <div class="flex flex-wrap items-center gap-5 mt-1">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="edit_approval_required_choice" id="edit_approval_yes" value="1"
                                               onchange="document.getElementById('edit_approval_required').value = this.value">
                                        <label class="form-check-label" for="edit_approval_yes">Yes</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="edit_approval_required_choice" id="edit_approval_no" value="0"
                                               onchange="document.getElementById('edit_approval_required').value = this.value">
                                        <label class="form-check-label" for="edit_approval_no">No</label>
                                    </div>
                                </div>
                                <div class="form-text ml-1">When set to Yes, a CEO signature is required before this expense can be recorded.</div>
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

        <script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>
        <?php
        /* Data island consumed by assets/js/expenses_type.js. */
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
        /* Flags for the script. It is a separate request, so it cannot test this
         * page's variables itself; these answer for it. Each is false on an ordinary
         * load, so nothing is shown unless there is something to show. */
        $__ocp_data["hasMessage"] = (!empty($swal_message));
        $__ocp_data["reopenAddModal"] = ($_SERVER['REQUEST_METHOD'] === 'POST' && $swal_message_type === 'error');
        $__ocp_data["openViewModal"] = ((bool) $view_expense);
        $__ocp_data["isError"] = ($swal_message_type === 'error');
        ocp_page_data("expenses_type", $__ocp_data);
        unset($__ocp_data);
        ?>
        <script src="<?php echo ocp_asset('assets/js/app.js'); ?>"></script>
        <script src="assets/js/expenses_type.js.php"></script>
    </body>
</html>
