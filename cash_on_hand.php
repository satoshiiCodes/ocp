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
// Check for session messages to display with SweetAlert2. This runs before the
// actions file, because the actions set these and then redirect back here.
$swal_message = '';
$swal_message_type = '';
if (isset($_SESSION['swal_message'])) {
    $swal_message = $_SESSION['swal_message'];
    $swal_message_type = $_SESSION['swal_message_type'];
    unset($_SESSION['swal_message']);
    unset($_SESSION['swal_message_type']);
}

// All of this page's actions live in one file: adding, editing and deleting a
// transaction. The forms post back to this page, so it is pulled in before
// anything is read or rendered.
if (!defined('OCP_CASH_ON_HAND_ACTIONS_RAN')) {
    require __DIR__ . '/actions/cash_on_hand-actions.php';
}

// All of this page's fetching lives in one file: it returns the variables the
// markup below needs, which are unpacked into this scope. It also carries the
// signed-in user's name, which the side menu prints.
$ocp_endpoint = require __DIR__ . '/api/cash_on_hand-endpoint.php';
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
        <title>Cash on Hand - OCP Construction</title>
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
                            <h1 class="page-title">Cash on Hand</h1>
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                <li class="breadcrumb-item active">Cash on Hand</li>
                            </ol>
                        </div>
                        
                        <!-- Current Balance Card -->
                        <div class="grid grid-cols-1 gap-6 mb-6">
                            <div class="min-w-0">
                                <div class="rounded-xl bg-linear-to-br from-brand-500 to-brand-700 p-6 text-white shadow-lg transition hover:-translate-y-0.5">
                                    <div class="flex justify-between items-center">
                                        <div>
                                            <h5 class="text-brand-100 mb-2">Current Cash on Hand</h5>
                                            <div class="text-4xl font-bold">₱<?php echo number_format($current_balance, 2); ?></div>
                                            <small class="text-brand-100">Last updated: <?php echo !empty($transactions) ? date('m-d-Y h:i A', strtotime($transactions[count($transactions)-1]['created_at'])) : 'No transactions yet'; ?></small>
                                        </div>
                                        <i class="fas fa-wallet fa-4x opacity-50"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Summary Cards -->
                        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2 mb-6">
                            <div class="min-w-0">
                                <div class="rounded-xl bg-success-600 p-6 text-white shadow-sm transition hover:-translate-y-0.5">
                                    <div class="flex justify-between items-center">
                                        <div>
                                            <h6 class="text-success-100 mb-1">Total Money In</h6>
                                            <h3 class="mb-0">₱<?php echo number_format($total_in, 2); ?></h3>
                                        </div>
                                        <i class="fas fa-arrow-down fa-3x opacity-50"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="min-w-0">
                                <div class="rounded-xl bg-danger-600 p-6 text-white shadow-sm transition hover:-translate-y-0.5">
                                    <div class="flex justify-between items-center">
                                        <div>
                                            <h6 class="text-danger-100 mb-1">Total Money Out</h6>
                                            <h3 class="mb-0">₱<?php echo number_format($total_out, 2); ?></h3>
                                        </div>
                                        <i class="fas fa-arrow-up fa-3x opacity-50"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Filter Section -->
                        <div class="card mb-6">
                            <div class="card-header">
                                <i class="fas fa-filter mr-1"></i>
                                Filter Transactions
                            </div>
                            <div class="card-body">
                                <form method="GET" action="" class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                                    <div class="min-w-0">
                                        <label for="filter_type" class="form-label">Transaction Type</label>
                                        <select class="form-select" id="filter_type" name="filter_type">
                                            <option value="">All Types</option>
                                            <option value="in" <?php echo $filter_type == 'in' ? 'selected' : ''; ?>>Money In</option>
                                            <option value="out" <?php echo $filter_type == 'out' ? 'selected' : ''; ?>>Money Out</option>
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
                                        <a href="cash_on_hand.php" class="btn btn-warning">
                                            <i class="fas fa-times"></i> Clear
                                        </a>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Transactions Table -->
                        <div class="card mb-6">
                            <div class="card-header flex items-center justify-between">
                                <div>
                                    <i class="fas fa-table mr-1"></i>
                                    Transaction History
                                </div>
                                <div>
                                    <a href="cash_on_hand_report_pdf.php?<?php echo http_build_query($_GET); ?>" class="btn btn-danger mr-2" target="_blank">
                                        <i class="fas fa-file-pdf mr-1"></i> Generate PDF
                                    </a>
                                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTransactionModal">
                                        <i class="fas fa-plus mr-1"></i> Add Transaction
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($transactions)): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped table-hover" id="datatablesSimple">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Type</th>
                                                <th>Amount (₱)</th>
                                                <th>Previous Balance</th>
                                                <th>New Balance</th>
                                                <th>Description</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($transactions as $transaction): ?>
                                            <tr class="<?php echo $transaction['transaction_type'] === 'in' ? 'bg-success-50' : 'bg-danger-50'; ?>">
                                                <td><?php echo date('m-d-Y', strtotime($transaction['transaction_date'])); ?></td>
                                                <td>
                                                    <?php if ($transaction['transaction_type'] === 'in'): ?>
                                                        <span class="badge badge-success">MONEY IN</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-danger">MONEY OUT</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end fw-bold">
                                                    <?php echo $transaction['transaction_type'] === 'in' ? '+' : '-'; ?>
                                                    ₱<?php echo number_format($transaction['amount'], 2); ?>
                                                </td>
                                                <td class="text-end">₱<?php echo number_format($transaction['previous_balance'], 2); ?></td>
                                                <td class="text-end fw-bold">₱<?php echo number_format($transaction['balance'], 2); ?></td>
                                                <td><?php echo htmlspecialchars($transaction['description']); ?></td>
                                                <td>
                                                    <div class="inline-flex items-center gap-1" role="group">
                                                        <form method="POST" action="" class="inline">
                                                            <button type="button" class="btn btn-sm btn-outline-primary view-transaction-btn" 
                                                                    data-id="<?php echo $transaction['id']; ?>"
                                                                    data-type="<?php echo $transaction['transaction_type']; ?>"
                                                                    data-amount="<?php echo $transaction['amount']; ?>"
                                                                    data-date="<?php echo $transaction['transaction_date']; ?>"
                                                                    data-description="<?php echo htmlspecialchars($transaction['description']); ?>"
                                                                    data-previous-balance="<?php echo $transaction['previous_balance']; ?>"
                                                                    data-balance="<?php echo $transaction['balance']; ?>"
                                                                    data-created="<?php echo $transaction['created_at']; ?>"
                                                                    data-creator="<?php echo htmlspecialchars(($transaction['user_firstname'] ?? '') . ' ' . ($transaction['user_lastname'] ?? 'System')); ?>">
                                                                <i class="fas fa-eye"></i>
                                                            </button>
                                                        </form>

                                                        <form method="POST" action="" class="inline">
                                                            <button type="button" class="btn btn-sm btn-warning edit-transaction-btn" 
                                                                    data-id="<?php echo $transaction['id']; ?>"
                                                                    data-type="<?php echo $transaction['transaction_type']; ?>"
                                                                    data-amount="<?php echo $transaction['amount']; ?>"
                                                                    data-date="<?php echo $transaction['transaction_date']; ?>"
                                                                    data-description="<?php echo htmlspecialchars($transaction['description']); ?>"
                                                                    data-bs-toggle="modal" data-bs-target="#editTransactionModal">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                        </form>

                                                        <form method="POST" action="" class="inline">
                                                            <button type="button" class="btn btn-sm btn-danger delete-transaction-btn" 
                                                                    data-id="<?php echo $transaction['id']; ?>">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr class="bg-slate-50 fw-bold">
                                                <td colspan="2" class="text-end">Totals:</td>
                                                <td class="text-end">₱<?php echo number_format($total_in - $total_out, 2); ?></td>
                                                <td colspan="5"></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                                <?php else: ?>
                                <p class="text-center">No transactions found. Add your first transaction using the button above.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </main>
                <?php include 'includes/footer.php';?>
            </div>
        </div>
        
        <!-- Add Transaction Modal -->
        <div class="modal" id="addTransactionModal" tabindex="-1" aria-labelledby="addTransactionModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addTransactionModalLabel">Add New Transaction</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="" id="addTransactionForm">
                        <div class="modal-body">
                            <div class="grid grid-cols-1 gap-6 xl:grid-cols-2 mb-4">
                                <div class="min-w-0">
                                    <div class="form-floating">
                                        <select class="form-control" id="add_transaction_type" name="transaction_type" required>
                                            <option value="">Select Type</option>
                                            <option value="in">Money In (Income)</option>
                                            <option value="out">Money Out (Expense)</option>
                                        </select>
                                        <label for="add_transaction_type">Transaction Type <span class="text-danger">*</span></label>
                                    </div>
                                </div>
                                <div class="min-w-0">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="add_amount" name="amount" 
                                               step="0.01" min="0.01" required placeholder="Amount"
                                               onkeyup="formatAmount(this)">
                                        <label for="add_amount">Amount (₱) <span class="text-danger">*</span></label>
                                        <small class="text-muted">Format: Automatically adds commas</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 gap-6 mb-4">
                                <div class="min-w-0">
                                    <div class="form-floating">
                                        <input type="date" class="form-control" id="add_transaction_date" name="transaction_date" 
                                               value="<?php echo date('Y-m-d'); ?>" required placeholder="Transaction Date">
                                        <label for="add_transaction_date">Transaction Date <span class="text-danger">*</span></label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <textarea class="form-control" id="add_description" name="description" 
                                          style="height: 100px" placeholder="Description" required></textarea>
                                <label for="add_description">Description <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle mr-2"></i>
                                <strong>Current Balance:</strong> ₱<?php echo number_format($current_balance, 2); ?>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" name="add_transaction" class="btn btn-primary">Add Transaction</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Edit Transaction Modal -->
        <div class="modal" id="editTransactionModal" tabindex="-1" aria-labelledby="editTransactionModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editTransactionModalLabel">Edit Transaction</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="" id="editTransactionForm">
                        <input type="hidden" name="transaction_id" id="edit_transaction_id">
                        <div class="modal-body">
                            <div class="grid grid-cols-1 gap-6 xl:grid-cols-2 mb-4">
                                <div class="min-w-0">
                                    <div class="form-floating">
                                        <select class="form-control" id="edit_transaction_type" name="transaction_type" required>
                                            <option value="">Select Type</option>
                                            <option value="in">Money In (Income)</option>
                                            <option value="out">Money Out (Expense)</option>
                                        </select>
                                        <label for="edit_transaction_type">Transaction Type <span class="text-danger">*</span></label>
                                    </div>
                                </div>
                                <div class="min-w-0">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="edit_amount" name="amount" 
                                               step="0.01" min="0.01" required placeholder="Amount"
                                               onkeyup="formatAmount(this)">
                                        <label for="edit_amount">Amount (₱) <span class="text-danger">*</span></label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 gap-6 mb-4">
                                <div class="min-w-0">
                                    <div class="form-floating">
                                        <input type="date" class="form-control" id="edit_transaction_date" name="transaction_date" required>
                                        <label for="edit_transaction_date">Transaction Date <span class="text-danger">*</span></label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <textarea class="form-control" id="edit_description" name="description" 
                                          style="height: 100px" placeholder="Description" required></textarea>
                                <label for="edit_description">Description <span class="text-danger">*</span></label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" name="edit_transaction" class="btn btn-primary">Update Transaction</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- View Transaction Modal -->
        <div class="modal" id="viewTransactionModal" tabindex="-1" aria-labelledby="viewTransactionModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="viewTransactionModalLabel">Transaction Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2 mb-4">
                            <div class="min-w-0">
                                <strong>Transaction Type:</strong>
                                <p class="text-muted" id="view_transaction_type"></p>
                            </div>
                            <div class="min-w-0">
                                <strong>Amount:</strong>
                                <p class="text-muted fw-bold" id="view_amount"></p>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2 mb-4">
                            <div class="min-w-0">
                                <strong>Transaction Date:</strong>
                                <p class="text-muted" id="view_transaction_date"></p>
                            </div>
                            <div class="min-w-0">
                                <strong>Previous Balance:</strong>
                                <p class="text-muted" id="view_previous_balance"></p>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2 mb-4">
                            <div class="min-w-0">
                                <strong>New Balance:</strong>
                                <p class="text-muted fw-bold" id="view_balance"></p>
                            </div>
                            <div class="min-w-0">
                                <strong>Added By:</strong>
                                <p class="text-muted" id="view_created_by"></p>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 gap-6 mb-4">
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
        
        <!-- Delete Confirmation Form (Hidden) -->
        <form method="POST" action="" id="deleteTransactionForm" style="display: none;">
            <input type="hidden" name="transaction_id" id="delete_transaction_id">
            <input type="hidden" name="delete_transaction" value="1">
        </form>
        
        <script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>
        <?php
        /* Data island consumed by assets/js/cash_on_hand.js. */
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
        ocp_page_data("cash_on_hand", $__ocp_data);
        unset($__ocp_data);
        ?>
        <script src="<?php echo ocp_asset('assets/js/app.js'); ?>"></script>
        <script src="assets/js/cash_on_hand.js.php"></script>
    </body>
</html>
