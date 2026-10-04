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
// All of this page's actions live in one file: adding, editing and deleting an
// expense. The forms post back to this page, so it is pulled in before anything
// is read or rendered.
if (!defined('OCP_EXPENSES_ACTIONS_RAN')) {
    require __DIR__ . '/actions/expenses-actions.php';
}

// Check for session messages to display with SweetAlert2. This runs after the
// actions file, which sets them, and before the endpoint, which may set one too.
$swal_message = '';
$swal_message_type = '';
if (isset($_SESSION['swal_message'])) {
    $swal_message = $_SESSION['swal_message'];
    $swal_message_type = $_SESSION['swal_message_type'];
    unset($_SESSION['swal_message']);
    unset($_SESSION['swal_message_type']);
}

// All of this page's fetching lives in one file: it returns the variables the
// markup below needs, which are unpacked into this scope. It carries the shared
// helper the page and the actions file both call.
$ocp_endpoint = require __DIR__ . '/api/expenses-endpoint.php';
foreach ($ocp_endpoint as $ocp_key => $ocp_value) {
    ${$ocp_key} = $ocp_value;
}
unset($ocp_endpoint, $ocp_key, $ocp_value);
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>Expenses - OCP Construction</title>
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
                            <h1 class="page-title">Expenses</h1>
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                <li class="breadcrumb-item active">Expenses</li>
                            </ol>
                        </div>
                        
                        <!-- Summary Cards: three across on one row. -->
                        <div class="grid grid-cols-1 gap-6 md:grid-cols-3 mb-6">
                            <div class="min-w-0">
                                <div class="rounded-xl bg-brand-600 p-6 text-white shadow-sm transition hover:-translate-y-0.5">
                                    <div class="flex justify-between items-center">
                                        <div>
                                            <h6 class="text-brand-100 mb-1">Total Expenses</h6>
                                            <h3 class="mb-0">₱<?php echo number_format($total_amount, 2); ?></h3>
                                        </div>
                                        <i class="fas fa-money-bill-wave fa-3x opacity-50"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="min-w-0">
                                <div class="rounded-xl bg-success-600 p-6 text-white shadow-sm transition hover:-translate-y-0.5">
                                    <div class="flex justify-between items-center">
                                        <div>
                                            <h6 class="text-success-100 mb-1">Total Entries</h6>
                                            <h3 class="mb-0"><?php echo count($expenses); ?></h3>
                                        </div>
                                        <i class="fas fa-list fa-3x opacity-50"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="min-w-0">
                                <div class="rounded-xl bg-info-600 p-6 text-white shadow-sm transition hover:-translate-y-0.5">
                                    <div class="flex justify-between items-center">
                                        <div>
                                            <h6 class="text-info-100 mb-1">Current Cash on Hand</h6>
                                            <h3 class="mb-0">₱<?php echo number_format($current_cash_balance, 2); ?></h3>
                                        </div>
                                        <i class="fas fa-wallet fa-3x opacity-50"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Filter Section -->
                        <div class="card mb-6">
                            <div class="card-header">
                                <i class="fas fa-filter mr-1"></i>
                                Filter Expenses
                            </div>
                            <div class="card-body">
                                <form method="GET" action="" class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                                    <div class="min-w-0">
                                        <label for="filter_type" class="form-label">Expense Type</label>
                                        <select class="form-select" id="filter_type" name="filter_type">
                                            <option value="">All Types</option>
                                            <?php foreach ($expense_types_from_db as $type): ?>
                                            <option value="<?php echo $type['id']; ?>" <?php echo $filter_type == $type['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($type['expense_name']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="min-w-0">
                                        <label for="start_date" class="form-label">Start Date</label>
                                        <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $filter_start_date; ?>">
                                    </div>
                                    <div class="min-w-0">
                                        <label for="end_date" class="form-label">End Date</label>
                                        <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $filter_end_date; ?>">
                                    </div>
                                    <div class="min-w-0 flex items-end">
                                        <button type="submit" class="btn btn-primary mr-2">
                                            <i class="fas fa-search"></i> Apply Filters
                                        </button>
                                        <a href="expenses.php" class="btn btn-warning">
                                            <i class="fas fa-times"></i> Clear
                                        </a>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Expenses Table -->
                        <div class="card mb-6">
                            <div class="card-header flex items-center justify-between">
                                <div>
                                    <i class="fas fa-table mr-1"></i>
                                    Expenses List
                                </div>
                                <div>
                                    <a href="expenses_report_pdf.php?<?php echo http_build_query($_GET); ?>" class="btn btn-danger mr-2" target="_blank">
                                        <i class="fas fa-file-pdf mr-1"></i> Generate PDF
                                    </a>
                                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
                                        <i class="fas fa-plus mr-1"></i> Add Expense
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($expenses)): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped table-hover" id="datatablesSimple">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Expense Type</th>
                                                <th>Amount (₱)</th>
                                                <th>Description</th>
                                                <th>Person</th>
                                                <th>Added By</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($expenses as $expense): ?>
                                            <tr>
                                                <td><?php echo date('m-d-Y', strtotime($expense['expense_date'])); ?></td>
                                                <td><?php echo htmlspecialchars($expense['expense_type_name'] ?? 'N/A'); ?></td>
                                                <td class="text-end fw-bold">₱<?php echo number_format($expense['amount'], 2); ?></td>
                                                <td><?php echo htmlspecialchars($expense['description'] ?? 'N/A'); ?></td>
                                                <td>
                                                    <?php 
                                                    if (!empty($expense['person_name'])) {
                                                        echo '<span class="badge badge-success">' . htmlspecialchars($expense['person_name']) . '</span>';
                                                    } elseif (!empty($expense['emp_firstname'])) {
                                                        echo '<span class="badge badge-info">' . 
                                                            htmlspecialchars($expense['emp_firstname'] . ' ' . $expense['emp_lastname']) . 
                                                            '</span>';
                                                    } elseif (!empty($expense['ceo_firstname'])) {
                                                        // Format CEO name
                                                        $ceo_name = $expense['ceo_firstname'];
                                                        if (!empty($expense['ceo_middlename'])) {
                                                            $ceo_name .= ' ' . substr($expense['ceo_middlename'], 0, 1) . '.';
                                                        }
                                                        $ceo_name .= ' ' . $expense['ceo_lastname'];
                                                        if (!empty($expense['ceo_suffix'])) {
                                                            $ceo_name .= ' ' . $expense['ceo_suffix'];
                                                        }
                                                        echo '<span class="badge badge-primary">' . 
                                                            htmlspecialchars($ceo_name) . 
                                                            '</span>';
                                                    } else {
                                                        echo '<span class="text-muted">—</span>';
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php 
                                                    if (!empty($expense['user_firstname'])) {
                                                        echo htmlspecialchars($expense['user_firstname'] . ' ' . $expense['user_lastname']);
                                                    } else {
                                                        echo '<span class="text-muted">System</span>';
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    // Approval state. An expense on an approval-required
                                                    // type is not settled until all three signatures are in,
                                                    // so it is marked and left out of the totals below.
                                                    $appr = $expense['approval_status'] ?? 'not_required';
                                                    if ($appr === 'approved') {
                                                        echo '<span class="badge badge-success">Approved</span>';
                                                    } elseif ($appr === 'partially_signed') {
                                                        $done = 0;
                                                        foreach (['reviewed_at', 'prepared_at', 'acknowledged_at'] as $f) {
                                                            if (!empty($expense[$f])) { $done++; }
                                                        }
                                                        echo '<span class="badge badge-warning">Signed ' . $done . '/3</span>';
                                                    } elseif ($appr === 'pending') {
                                                        echo '<span class="badge badge-warning">Pending Approval</span>';
                                                    } else {
                                                        echo '<span class="badge badge-neutral">—</span>';
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <div class="action-cell" role="group">
                                                        <?php
                                                        // Offered only when this expense is still awaiting
                                                        // signatures and the signed-in user is the role that
                                                        // signs the step currently open. The handler checks
                                                        // both again, so this only drives the interface.
                                                        $ocp_appr = $expense['approval_status'] ?? 'not_required';
                                                        $ocp_needs = in_array($ocp_appr, ['pending', 'partially_signed'], true);
                                                        $ocp_can = false;
                                                        $ocp_open = null;

                                                        if ($ocp_needs && !empty($my_sign_step)) {
                                                            // Which step is open: the first unsigned one.
                                                            if (empty($expense['reviewed_at'])) {
                                                                $ocp_open = 'reviewed';
                                                            } elseif (empty($expense['prepared_at'])) {
                                                                $ocp_open = 'prepared';
                                                            } elseif (empty($expense['acknowledged_at'])) {
                                                                $ocp_open = 'acknowledged';
                                                            }

                                                            // Accounting signs only the first step; a CEO
                                                            // signs either of the two CEO steps.
                                                            $ocp_can = ($ocp_open === 'reviewed' && $my_sign_step === 'reviewed')
                                                                || ($ocp_open === 'prepared' && $my_sign_step === 'prepared')
                                                                || ($ocp_open === 'acknowledged'
                                                                    && in_array($my_sign_step, ['prepared', 'acknowledged'], true));
                                                        }
                                                        ?>

                                                        <!-- Row 1: the approval control, when there is one to offer. -->
                                                        <?php if ($ocp_can): ?>
                                                        <div class="action-buttons action-buttons--row">
                                                            <?php
                                                            // One name per step, used for both the alert and the
                                                            // column, so the two can never disagree.
                                                            //
                                                            // The signing order is that of the map below: Accounting
                                                            // first, then the CEO twice. The first step is
                                                            // "Prepared by" - the line the Accounting officer signs.
                                                            $ocp_step_names = [
                                                                'reviewed'     => 'Prepared by',
                                                                'prepared'     => 'Reviewed by',
                                                                'acknowledged' => 'Acknowledged by',
                                                            ];
                                                            $ocp_step_name = $ocp_step_names[$ocp_open] ?? '';
                                                            ?>
                                                            <button type="button" class="btn btn-sm bg-info-600 text-white sign-expense-btn"
                                                                    data-id="<?php echo $expense['id']; ?>"
                                                                    data-step="<?php echo $ocp_open; ?>"
                                                                    data-step-name="<?php echo $ocp_step_name; ?>"
                                                                    data-reviewed="<?php echo !empty($expense['reviewed_at']) ? '1' : '0'; ?>"
                                                                    data-prepared="<?php echo !empty($expense['prepared_at']) ? '1' : '0'; ?>"
                                                                    data-acknowledged="<?php echo !empty($expense['acknowledged_at']) ? '1' : '0'; ?>">
                                                                <i class="fas fa-check-circle mr-1"></i>Approve
                                                            </button>
                                                        </div>
                                                        <?php endif; ?>

                                                        <!-- Row 2: view, edit, delete. -->
                                                        <div class="action-buttons action-buttons--row">
                                                        <form method="POST" action="" class="inline">
                                                            <button type="button" class="btn btn-sm btn-outline-primary view-expense-btn" 
                                                                    data-id="<?php echo $expense['id']; ?>"
                                                                    data-type="<?php echo htmlspecialchars($expense['expense_type_name'] ?? ''); ?>"
                                                                    data-amount="<?php echo $expense['amount']; ?>"
                                                                    data-date="<?php echo $expense['expense_date']; ?>"
                                                                    data-description="<?php echo htmlspecialchars($expense['description'] ?? ''); ?>"
                                                                    data-person-name="<?php echo htmlspecialchars($expense['person_name'] ?? ''); ?>"
                                                                    data-employee="<?php 
                                                                        if (!empty($expense['emp_firstname'])) {
                                                                            echo htmlspecialchars($expense['emp_firstname'] . ' ' . $expense['emp_lastname']);
                                                                        } elseif (!empty($expense['ceo_firstname'])) {
                                                                            $ceo_name = $expense['ceo_firstname'];
                                                                            if (!empty($expense['ceo_middlename'])) {
                                                                                $ceo_name .= ' ' . substr($expense['ceo_middlename'], 0, 1) . '.';
                                                                            }
                                                                            $ceo_name .= ' ' . $expense['ceo_lastname'];
                                                                            if (!empty($expense['ceo_suffix'])) {
                                                                                $ceo_name .= ' ' . $expense['ceo_suffix'];
                                                                            }
                                                                            echo htmlspecialchars($ceo_name);
                                                                        } else {
                                                                            echo '';
                                                                        }
                                                                    ?>"
                                                                    data-created="<?php echo $expense['created_at']; ?>"
                                                                    data-creator="<?php echo htmlspecialchars(($expense['user_firstname'] ?? '') . ' ' . ($expense['user_lastname'] ?? 'System')); ?>">
                                                                <i class="fas fa-eye"></i>
                                                            </button>
                                                        </form>

                                                        <form method="POST" action="" class="inline">
                                                            <button type="button" class="btn btn-sm btn-warning edit-expense-btn" 
                                                                    data-id="<?php echo $expense['id']; ?>"
                                                                    data-type-id="<?php echo $expense['expense_type_id']; ?>"
                                                                    data-type-name="<?php echo htmlspecialchars($expense['expense_type_name'] ?? ''); ?>"
                                                                    data-amount="<?php echo $expense['amount']; ?>"
                                                                    data-date="<?php echo $expense['expense_date']; ?>"
                                                                    data-full-description="<?php echo htmlspecialchars($expense['description'] ?? ''); ?>"
                                                                    data-person-name="<?php echo htmlspecialchars($expense['person_name'] ?? ''); ?>"
                                                                    data-employee-id="<?php echo $expense['employee_id']; ?>"
                                                                    data-ceo-id="<?php echo $expense['ceo_id']; ?>"
                                                                    data-bs-toggle="modal" data-bs-target="#editExpenseModal">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                        </form>

                                                        <form method="POST" action="" class="inline">
                                                            <button type="button" class="btn btn-sm btn-danger delete-expense-btn" 
                                                                    data-id="<?php echo $expense['id']; ?>">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr class="bg-slate-50 fw-bold">
                                                <td colspan="2" class="text-end">Total:</td>
                                                <td class="text-end">₱<?php echo number_format($total_amount, 2); ?></td>
                                                <td colspan="5">
                                                    <?php if (!empty($pending_amount)): ?>
                                                    <!-- Shown so the difference between this total and the
                                                         sum of the rows is explained rather than puzzling. -->
                                                    <span class="text-muted font-normal">
                                                        (excludes ₱<?php echo number_format($pending_amount, 2); ?> awaiting approval)
                                                    </span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                                <?php else: ?>
                                <p class="text-center">No expenses found. Add your first expense using the button above.</p>
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
                        <h5 class="modal-title" id="addExpenseModalLabel">Add New Expense</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="" id="addExpenseForm">
                        <div class="modal-body">
                            <div class="form-floating mb-4">
                                <select class="form-control" id="add_expense_type_id" name="expense_type_id" required onchange="toggleAddFields()">
                                    <option value="">Select Expense Type</option>
                                    <?php foreach ($expense_types_from_db as $type): ?>
                                    <option value="<?php echo $type['id']; ?>" data-name="<?php echo htmlspecialchars($type['expense_name']); ?>">
                                        <?php echo htmlspecialchars($type['expense_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="add_expense_type_id">Expense Type <span class="text-danger">*</span></label>
                            </div>
                            
                            <!-- Person Selection Type - Now in one row -->
                            <div class="mb-4" id="add_person_selection_container" style="display: none;">
                                <label class="form-label block">Person Selection Method <span class="text-danger">*</span></label>
                                <div class="flex flex-wrap items-center gap-5 mt-1">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="person_selection_type" id="add_person_selection_none" value="none" checked onchange="toggleAddPersonFields()">
                                        <label class="form-check-label" for="add_person_selection_none">
                                            None
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="person_selection_type" id="add_person_selection_employee" value="employee" onchange="toggleAddPersonFields()">
                                        <label class="form-check-label" for="add_person_selection_employee">
                                            Employee
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="person_selection_type" id="add_person_selection_ceo" value="ceo" onchange="toggleAddPersonFields()">
                                        <label class="form-check-label" for="add_person_selection_ceo">
                                            CEO
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="person_selection_type" id="add_person_selection_manual" value="manual" onchange="toggleAddPersonFields()">
                                        <label class="form-check-label" for="add_person_selection_manual">
                                            Manual
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Employee dropdown -->
                            <div class="form-floating mb-4" id="add_employee_field" style="display: none;">
                                <select class="form-control" id="add_employee_id" name="employee_id">
                                    <option value="">Select Employee</option>
                                    <?php foreach ($employees as $employee): ?>
                                    <option value="<?php echo $employee['id']; ?>">
                                        <?php 
                                        // Format name as: Firstname M. Lastname Suffix
                                        $formatted_name = $employee['firstname'];
                                        
                                        // Add middle initial if exists
                                        if (!empty($employee['middlename'])) {
                                            $formatted_name .= ' ' . substr($employee['middlename'], 0, 1) . '.';
                                        }
                                        
                                        // Add lastname
                                        $formatted_name .= ' ' . $employee['lastname'];
                                        
                                        // Add suffix if exists
                                        if (!empty($employee['suffix'])) {
                                            $formatted_name .= ' ' . $employee['suffix'];
                                        }
                                        
                                        echo htmlspecialchars($formatted_name);
                                        ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="add_employee_id">Select Employee</label>
                            </div>
                            
                            <!-- CEO dropdown -->
                            <div class="form-floating mb-4" id="add_ceo_field" style="display: none;">
                                <select class="form-control" id="add_ceo_id" name="ceo_id">
                                    <option value="">Select CEO</option>
                                    <?php foreach ($ceo_users as $ceo_user): ?>
                                    <option value="<?php echo $ceo_user['id']; ?>">
                                        <?php 
                                        // Format name as: Firstname M. Lastname Suffix
                                        $formatted_name = $ceo_user['firstname'];
                                        
                                        // Add middle initial if exists
                                        if (!empty($ceo_user['middlename'])) {
                                            $formatted_name .= ' ' . substr($ceo_user['middlename'], 0, 1) . '.';
                                        }
                                        
                                        // Add lastname
                                        $formatted_name .= ' ' . $ceo_user['lastname'];
                                        
                                        // Add suffix if exists
                                        if (!empty($ceo_user['suffix'])) {
                                            $formatted_name .= ' ' . $ceo_user['suffix'];
                                        }
                                        
                                        echo htmlspecialchars($formatted_name);
                                        ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="add_ceo_id">Select CEO</label>
                            </div>
                            
                            <!-- Manual Person Name Input -->
                            <div class="form-floating mb-4" id="add_person_name_field" style="display: none;">
                                <input type="text" class="form-control" id="add_person_name_input" name="person_name_input" placeholder="Enter person name">
                                <label for="add_person_name_input">Person Name</label>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <input type="text" class="form-control" id="add_amount" name="amount" 
                                       step="0.01" min="0.01" required placeholder="Amount"
                                       onkeyup="formatAmount(this)">
                                <label for="add_amount">Amount (₱) <span class="text-danger">*</span></label>
                                <small class="text-muted">Format: Automatically adds commas (e.g., 1,000.00)</small>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <input type="date" class="form-control" id="add_expense_date" name="expense_date" 
                                       value="<?php echo date('Y-m-d'); ?>" required placeholder="Expense Date">
                                <label for="add_expense_date">Expense Date <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <textarea class="form-control" id="add_description" name="description" 
                                          style="height: 100px" placeholder="Description"></textarea>
                                <label for="add_description">Notes</label>
                            </div>
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle mr-2"></i>
                                <strong>Current Cash Balance:</strong> ₱<?php echo number_format($current_cash_balance, 2); ?>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" name="add_expense" class="btn btn-primary">Add Expense</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Edit Expense Modal -->
        <div class="modal" id="editExpenseModal" tabindex="-1" aria-labelledby="editExpenseModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editExpenseModalLabel">Edit Expense</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="" id="editExpenseForm">
                        <input type="hidden" name="expense_id" id="edit_expense_id">
                        <div class="modal-body">
                            <div class="form-floating mb-4">
                                <select class="form-control" id="edit_expense_type_id" name="expense_type_id" required onchange="toggleEditFields()">
                                    <option value="">Select Expense Type</option>
                                    <?php foreach ($expense_types_from_db as $type): ?>
                                    <option value="<?php echo $type['id']; ?>" data-name="<?php echo htmlspecialchars($type['expense_name']); ?>">
                                        <?php echo htmlspecialchars($type['expense_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="edit_expense_type_id">Expense Type <span class="text-danger">*</span></label>
                            </div>
                            
                            <!-- Person Selection Type - Now in one row -->
                            <div class="mb-4" id="edit_person_selection_container" style="display: none;">
                                <label class="form-label block">Person Selection Method <span class="text-danger">*</span></label>
                                <div class="flex flex-wrap items-center gap-5 mt-1">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="person_selection_type" id="edit_person_selection_none" value="none" onchange="toggleEditPersonFields()">
                                        <label class="form-check-label" for="edit_person_selection_none">
                                            None
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="person_selection_type" id="edit_person_selection_employee" value="employee" onchange="toggleEditPersonFields()">
                                        <label class="form-check-label" for="edit_person_selection_employee">
                                            Employee
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="person_selection_type" id="edit_person_selection_ceo" value="ceo" onchange="toggleEditPersonFields()">
                                        <label class="form-check-label" for="edit_person_selection_ceo">
                                            CEO
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="person_selection_type" id="edit_person_selection_manual" value="manual" onchange="toggleEditPersonFields()">
                                        <label class="form-check-label" for="edit_person_selection_manual">
                                            Manual
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Employee dropdown -->
                            <div class="form-floating mb-4" id="edit_employee_field" style="display: none;">
                                <select class="form-control" id="edit_employee_id" name="employee_id">
                                    <option value="">Select Employee</option>
                                    <?php foreach ($employees as $employee): ?>
                                    <option value="<?php echo $employee['id']; ?>">
                                        <?php 
                                        $formatted_name = $employee['firstname'];
                                        if (!empty($employee['middlename'])) {
                                            $formatted_name .= ' ' . substr($employee['middlename'], 0, 1) . '.';
                                        }
                                        $formatted_name .= ' ' . $employee['lastname'];
                                        if (!empty($employee['suffix'])) {
                                            $formatted_name .= ' ' . $employee['suffix'];
                                        }
                                        echo htmlspecialchars($formatted_name);
                                        ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="edit_employee_id">Select Employee</label>
                            </div>
                            
                            <!-- CEO dropdown -->
                            <div class="form-floating mb-4" id="edit_ceo_field" style="display: none;">
                                <select class="form-control" id="edit_ceo_id" name="ceo_id">
                                    <option value="">Select CEO</option>
                                    <?php foreach ($ceo_users as $ceo_user): ?>
                                    <option value="<?php echo $ceo_user['id']; ?>">
                                        <?php 
                                        $formatted_name = $ceo_user['firstname'];
                                        if (!empty($ceo_user['middlename'])) {
                                            $formatted_name .= ' ' . substr($ceo_user['middlename'], 0, 1) . '.';
                                        }
                                        $formatted_name .= ' ' . $ceo_user['lastname'];
                                        if (!empty($ceo_user['suffix'])) {
                                            $formatted_name .= ' ' . $ceo_user['suffix'];
                                        }
                                        echo htmlspecialchars($formatted_name);
                                        ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="edit_ceo_id">Select CEO</label>
                            </div>
                            
                            <!-- Manual Person Name Input -->
                            <div class="form-floating mb-4" id="edit_person_name_field" style="display: none;">
                                <input type="text" class="form-control" id="edit_person_name_input" name="person_name_input" placeholder="Enter person name">
                                <label for="edit_person_name_input">Person Name</label>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <input type="text" class="form-control" id="edit_amount" name="amount" 
                                       step="0.01" min="0.01" required placeholder="Amount"
                                       onkeyup="formatAmount(this)">
                                <label for="edit_amount">Amount (₱) <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <input type="date" class="form-control" id="edit_expense_date" name="expense_date" required>
                                <label for="edit_expense_date">Expense Date <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <textarea class="form-control" id="edit_description" name="description" 
                                          style="height: 100px" placeholder="Additional Notes"></textarea>
                                <label for="edit_description">Additional Notes</label>
                            </div>
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle mr-2"></i>
                                <strong>Current Cash Balance:</strong> ₱<?php echo number_format($current_cash_balance, 2); ?>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" name="edit_expense" class="btn btn-primary">Update Expense</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- View Expense Modal -->
        <div class="modal" id="viewExpenseModal" tabindex="-1" aria-labelledby="viewExpenseModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="viewExpenseModalLabel">Expense Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2 mb-4">
                            <div class="min-w-0">
                                <strong>Expense Type:</strong>
                                <p class="text-muted" id="view_expense_type"></p>
                            </div>
                            <div class="min-w-0">
                                <strong>Amount:</strong>
                                <p class="text-muted fw-bold text-success" id="view_amount"></p>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2 mb-4">
                            <div class="min-w-0">
                                <strong>Expense Date:</strong>
                                <p class="text-muted" id="view_expense_date"></p>
                            </div>
                            <div class="min-w-0">
                                <strong>Person:</strong>
                                <p class="text-muted" id="view_person"></p>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2 mb-4">
                            <div class="min-w-0">
                                <strong>Added By:</strong>
                                <p class="text-muted" id="view_created_by"></p>
                            </div>
                            <div class="min-w-0">
                                <strong>Date Added:</strong>
                                <p class="text-muted" id="view_created_at"></p>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 gap-6 mb-4">
                            <div class="min-w-0">
                                <strong>Description:</strong>
                                <p class="text-muted" id="view_description"></p>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Approve / Sign Modal -->
        <!--
             Three signatures, in order: Reviewed by (Admin/Admin/Accounting), then Prepared by
             and Acknowledged by (Admin/Admin/CEO). The step that is open to the signed-in user is
             decided by the server and handed to the script through data attributes when the dialog
             is opened, so the markup itself stays a single instance rather than one per row.
        -->
        <div class="modal fade" id="approveExpenseModal" tabindex="-1" aria-labelledby="approveExpenseModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="approveExpenseModalLabel">Sign Expense</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="" id="approveExpenseForm">
                        <input type="hidden" name="approve_expense" value="1">
                        <input type="hidden" name="expense_id" id="approve_expense_id">
                        <input type="hidden" name="signature_data" id="approve_signature_data">
                        <div class="modal-body">
                            <div class="alert alert-info">
                                <span class="alert-flow">
                                    You are about to sign as <strong id="approve_step_label">—</strong>.
                                    This is recorded against your account with today's date.
                                </span>
                            </div>
                            <!-- The step named here is set from the button when the dialog opens, and is the
                                 same name as that step's column below: the flow is Prepared by, Reviewed
                                 by, Acknowledged by. -->

                            <!-- Routing: who signs, in the order they sign.
                                 Same pattern as Routing Progress on the purchase request pages.
                                 The rule here is the SIGNING order, which is not the order of the
                                 labels: the line printed "Prepared by" is where Accounting signs
                                 first, then the CEO on "Reviewed by", then the CEO again on
                                 "Acknowledged by". -->
                            <div class="overflow-x-auto py-1 mb-4">
                                <!-- The connector runs through the centre of the circles. Measured in
                                     the browser: with a 40px circle (h-10) the centre sits 36px from
                                     the top of the flex row, so the line is placed at 34.5px - its own
                                     3px height puts its centre on 36. -->
                                <div class="flex justify-center py-4 relative min-w-full before:absolute before:top-[34.5px] before:left-0 before:right-0 before:h-[3px] before:bg-slate-200 before:content-['']">
                                    <?php
                                    // Three lines per column: the role, the step's name, then the state.
                                    // The step names are the flow in order - Prepared by, Reviewed by,
                                    // Acknowledged by - and match the alert and the action handler.
                                    //
                                    // The keys are the column prefixes in the database. The first step is
                                    // "Prepared by" and is the column reviewed_*; the labels were crossed
                                    // here before, which put the wrong name on the first two steps.
                                    $ocp_steps = [
                                        'reviewed'     => ['label' => 'Prepared by',     'dept' => 'Accounting', 'icon' => 'fa-calculator'],
                                        'prepared'     => ['label' => 'Reviewed by',     'dept' => 'CEO',        'icon' => 'fa-user-check'],
                                        'acknowledged' => ['label' => 'Acknowledged by', 'dept' => 'CEO',        'icon' => 'fa-user-tie'],
                                    ];
                                    foreach ($ocp_steps as $key => $st):
                                    ?>
                                    <div class="shrink-0 min-w-[130px] max-w-[160px] text-center relative px-1 z-10 mx-1">
                                        <div id="cir_<?php echo $key; ?>"
                                             class="h-10 w-10 rounded-full border-[3px] flex items-center justify-center text-lg mx-auto mb-2 relative z-10 transition-all bg-slate-50 border-slate-200 text-slate-500">
                                            <i class="fas <?php echo $st['icon']; ?>"></i>
                                        </div>
                                        <div class="text-xs font-semibold leading-tight"><?php echo $st['dept']; ?></div>
                                        <div class="text-[11px] text-muted leading-tight"><?php echo $st['label']; ?></div>
                                        <div class="text-[11px] text-slate-500 mt-0.5" id="stat_<?php echo $key; ?>">Pending</div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <label class="form-label block">Signature <span class="text-danger">*</span></label>
                            <canvas id="expenseSignatureCanvas"
                                    class="signature-pad w-full h-48 border border-slate-300 rounded touch-none"
                                    width="500" height="200"></canvas>
                            <div class="mt-2 flex justify-between items-center">
                                <small class="text-muted">Sign with the mouse or a finger.</small>
                                <button type="button" class="btn btn-sm btn-secondary" id="clearExpenseSignatureBtn">Clear</button>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn bg-info-600 text-white" id="submitApproveExpenseBtn">Sign</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Form (Hidden) -->
        <form method="POST" action="" id="deleteExpenseForm" style="display: none;">
            <input type="hidden" name="expense_id" id="delete_expense_id">
            <input type="hidden" name="delete_expense" value="1">
        </form>
        
        <script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>
        <!-- Signature capture for the approval steps, the same library the PO approval uses. -->
        <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>
        <?php
        /* Data island consumed by assets/js/expenses.js. */
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
        ocp_page_data("expenses", $__ocp_data);
        unset($__ocp_data);
        ?>
        <script src="<?php echo ocp_asset('assets/js/app.js'); ?>"></script>
        <script src="assets/js/expenses.js.php"></script>
    </body>
</html>
