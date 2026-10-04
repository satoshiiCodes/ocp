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

// Process form submission for adding cash transaction
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_transaction'])) {
    // Remove commas from amount before processing
    $amount = str_replace(',', '', $_POST['amount']);
    $transaction_type = $_POST['transaction_type'];
    $amount = floatval($amount);
    $transaction_date = $_POST['transaction_date'];
    $description = trim($_POST['description']);
    
    // Basic validation
    if ($amount <= 0 || empty($transaction_date) || empty($description)) {
        $_SESSION['swal_message'] = 'Please fill in all required fields and ensure amount is greater than 0.';
        $_SESSION['swal_message_type'] = 'error';
    } else {
        try {
            // Start transaction
            $pdo->beginTransaction();
            
            // Get current cash on hand balance
            $balanceStmt = $pdo->prepare("SELECT balance FROM cash_on_hand ORDER BY id DESC LIMIT 1");
            $balanceStmt->execute();
            $currentBalance = $balanceStmt->fetch(PDO::FETCH_ASSOC);
            
            $previous_balance = $currentBalance ? $currentBalance['balance'] : 0;
            
            // Calculate new balance
            if ($transaction_type === 'in') {
                $new_balance = $previous_balance + $amount;
            } else {
                $new_balance = $previous_balance - $amount;
                
                // Check if sufficient balance
                if ($new_balance < 0) {
                    $pdo->rollBack();
                    $_SESSION['swal_message'] = 'Insufficient cash on hand balance!';
                    $_SESSION['swal_message_type'] = 'error';
                    header("Location: ".$_SERVER['PHP_SELF']);
                    exit();
                }
            }
            
            // Insert transaction (removed reference field)
            $insertStmt = $pdo->prepare("INSERT INTO cash_on_hand (transaction_type, amount, previous_balance, balance, transaction_date, description, created_by) VALUES (:transaction_type, :amount, :previous_balance, :balance, :transaction_date, :description, :created_by)");
            $insertStmt->bindParam(':transaction_type', $transaction_type);
            $insertStmt->bindParam(':amount', $amount);
            $insertStmt->bindParam(':previous_balance', $previous_balance);
            $insertStmt->bindParam(':balance', $new_balance);
            $insertStmt->bindParam(':transaction_date', $transaction_date);
            $insertStmt->bindParam(':description', $description);
            $insertStmt->bindParam(':created_by', $_SESSION['user_id']);
            
            if ($insertStmt->execute()) {
                $pdo->commit();
                $_SESSION['swal_message'] = 'Transaction added successfully!';
                $_SESSION['swal_message_type'] = 'success';
                
                // Redirect to refresh the page
                header("Location: ".$_SERVER['PHP_SELF']);
                exit();
            } else {
                $pdo->rollBack();
                $_SESSION['swal_message'] = 'Error adding transaction. Please try again.';
                $_SESSION['swal_message_type'] = 'error';
            }
        } catch(PDOException $e) {
            $pdo->rollBack();
            $_SESSION['swal_message'] = 'Database error: ' . $e->getMessage();
            $_SESSION['swal_message_type'] = 'error';
        }
    }
}

// Process edit transaction
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_transaction'])) {
    $transaction_id = (int)$_POST['transaction_id'];
    $amount = str_replace(',', '', $_POST['amount']);
    $transaction_type = $_POST['transaction_type'];
    $amount = floatval($amount);
    $transaction_date = $_POST['transaction_date'];
    $description = trim($_POST['description']);
    
    // Basic validation
    if ($amount <= 0 || empty($transaction_date) || empty($description)) {
        $_SESSION['swal_message'] = 'Please fill in all required fields and ensure amount is greater than 0.';
        $_SESSION['swal_message_type'] = 'error';
    } else {
        try {
            // Start transaction
            $pdo->beginTransaction();
            
            // Get the transaction being edited
            $oldStmt = $pdo->prepare("SELECT * FROM cash_on_hand WHERE id = :id");
            $oldStmt->bindParam(':id', $transaction_id);
            $oldStmt->execute();
            $oldTransaction = $oldStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$oldTransaction) {
                $pdo->rollBack();
                $_SESSION['swal_message'] = 'Transaction not found.';
                $_SESSION['swal_message_type'] = 'error';
                header("Location: ".$_SERVER['PHP_SELF']);
                exit();
            }
            
            // Get all transactions after this one to recalculate balances
            $subsequentStmt = $pdo->prepare("SELECT * FROM cash_on_hand WHERE id > :id ORDER BY id ASC");
            $subsequentStmt->bindParam(':id', $transaction_id);
            $subsequentStmt->execute();
            $subsequentTransactions = $subsequentStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Delete the transaction being edited
            $deleteStmt = $pdo->prepare("DELETE FROM cash_on_hand WHERE id = :id");
            $deleteStmt->bindParam(':id', $transaction_id);
            $deleteStmt->execute();
            
            // Get the previous balance (from the transaction before this one)
            $prevStmt = $pdo->prepare("SELECT balance FROM cash_on_hand WHERE id < :id ORDER BY id DESC LIMIT 1");
            $prevStmt->bindParam(':id', $transaction_id);
            $prevStmt->execute();
            $prevBalance = $prevStmt->fetch(PDO::FETCH_ASSOC);
            
            $previous_balance = $prevBalance ? $prevBalance['balance'] : 0;
            
            // Calculate new balance for edited transaction
            if ($transaction_type === 'in') {
                $new_balance = $previous_balance + $amount;
            } else {
                $new_balance = $previous_balance - $amount;
                
                // Check if sufficient balance
                if ($new_balance < 0) {
                    $pdo->rollBack();
                    $_SESSION['swal_message'] = 'Insufficient cash on hand balance!';
                    $_SESSION['swal_message_type'] = 'error';
                    header("Location: ".$_SERVER['PHP_SELF']);
                    exit();
                }
            }
            
            // Insert the edited transaction (removed reference field)
            $insertStmt = $pdo->prepare("INSERT INTO cash_on_hand (id, transaction_type, amount, previous_balance, balance, transaction_date, description, created_by, created_at) 
                                        VALUES (:id, :transaction_type, :amount, :previous_balance, :balance, :transaction_date, :description, :created_by, :created_at)");
            $insertStmt->bindParam(':id', $transaction_id);
            $insertStmt->bindParam(':transaction_type', $transaction_type);
            $insertStmt->bindParam(':amount', $amount);
            $insertStmt->bindParam(':previous_balance', $previous_balance);
            $insertStmt->bindParam(':balance', $new_balance);
            $insertStmt->bindParam(':transaction_date', $transaction_date);
            $insertStmt->bindParam(':description', $description);
            $insertStmt->bindParam(':created_by', $_SESSION['user_id']);
            $insertStmt->bindParam(':created_at', $oldTransaction['created_at']);
            $insertStmt->execute();
            
            // Recalculate balances for subsequent transactions
            $running_balance = $new_balance;
            
            foreach ($subsequentTransactions as $subsequent) {
                $running_previous = $running_balance;
                
                if ($subsequent['transaction_type'] === 'in') {
                    $running_balance += $subsequent['amount'];
                } else {
                    $running_balance -= $subsequent['amount'];
                }
                
                $updateStmt = $pdo->prepare("UPDATE cash_on_hand SET previous_balance = :prev_balance, balance = :balance WHERE id = :id");
                $updateStmt->bindParam(':prev_balance', $running_previous);
                $updateStmt->bindParam(':balance', $running_balance);
                $updateStmt->bindParam(':id', $subsequent['id']);
                $updateStmt->execute();
            }
            
            $pdo->commit();
            $_SESSION['swal_message'] = 'Transaction updated successfully!';
            $_SESSION['swal_message_type'] = 'success';
            
            // Redirect to refresh the page
            header("Location: ".$_SERVER['PHP_SELF']);
            exit();
            
        } catch(PDOException $e) {
            $pdo->rollBack();
            $_SESSION['swal_message'] = 'Database error: ' . $e->getMessage();
            $_SESSION['swal_message_type'] = 'error';
        }
    }
}

// Process delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_transaction'])) {
    $transaction_id = (int)$_POST['transaction_id'];
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Get the transaction being deleted
        $oldStmt = $pdo->prepare("SELECT * FROM cash_on_hand WHERE id = :id");
        $oldStmt->bindParam(':id', $transaction_id);
        $oldStmt->execute();
        $oldTransaction = $oldStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$oldTransaction) {
            $pdo->rollBack();
            $_SESSION['swal_message'] = 'Transaction not found.';
            $_SESSION['swal_message_type'] = 'error';
            header("Location: ".$_SERVER['PHP_SELF']);
            exit();
        }
        
        // Get all transactions after this one
        $subsequentStmt = $pdo->prepare("SELECT * FROM cash_on_hand WHERE id > :id ORDER BY id ASC");
        $subsequentStmt->bindParam(':id', $transaction_id);
        $subsequentStmt->execute();
        $subsequentTransactions = $subsequentStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Delete the transaction
        $deleteStmt = $pdo->prepare("DELETE FROM cash_on_hand WHERE id = :id");
        $deleteStmt->bindParam(':id', $transaction_id);
        $deleteStmt->execute();
        
        // Get the previous balance (from the transaction before the deleted one)
        $prevStmt = $pdo->prepare("SELECT balance FROM cash_on_hand WHERE id < :id ORDER BY id DESC LIMIT 1");
        $prevStmt->bindParam(':id', $transaction_id);
        $prevStmt->execute();
        $prevBalance = $prevStmt->fetch(PDO::FETCH_ASSOC);
        
        $running_balance = $prevBalance ? $prevBalance['balance'] : 0;
        
        // Recalculate balances for subsequent transactions
        foreach ($subsequentTransactions as $subsequent) {
            $running_previous = $running_balance;
            
            if ($subsequent['transaction_type'] === 'in') {
                $running_balance += $subsequent['amount'];
            } else {
                $running_balance -= $subsequent['amount'];
            }
            
            $updateStmt = $pdo->prepare("UPDATE cash_on_hand SET previous_balance = :prev_balance, balance = :balance WHERE id = :id");
            $updateStmt->bindParam(':prev_balance', $running_previous);
            $updateStmt->bindParam(':balance', $running_balance);
            $updateStmt->bindParam(':id', $subsequent['id']);
            $updateStmt->execute();
        }
        
        $pdo->commit();
        $_SESSION['swal_message'] = 'Transaction deleted successfully!';
        $_SESSION['swal_message_type'] = 'success';
        
        header("Location: ".$_SERVER['PHP_SELF']);
        exit();
        
    } catch(PDOException $e) {
        $pdo->rollBack();
        $_SESSION['swal_message'] = 'Database error: ' . $e->getMessage();
        $_SESSION['swal_message_type'] = 'error';
    }
}

// Get cash on hand transactions
try {
    // Get filter parameters
    $filter_type = isset($_GET['filter_type']) ? $_GET['filter_type'] : '';
    $filter_start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
    $filter_end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
    
    $query = "
        SELECT c.*, 
               u.firstname as user_firstname, u.lastname as user_lastname
        FROM cash_on_hand c
        LEFT JOIN users u ON c.created_by = u.id
        WHERE 1=1
    ";
    
    $params = [];
    
    if (!empty($filter_type)) {
        $query .= " AND c.transaction_type = :transaction_type";
        $params[':transaction_type'] = $filter_type;
    }
    
    if (!empty($filter_start_date)) {
        $query .= " AND DATE(c.transaction_date) >= :start_date";
        $params[':start_date'] = $filter_start_date;
    }
    
    if (!empty($filter_end_date)) {
        $query .= " AND DATE(c.transaction_date) <= :end_date";
        $params[':end_date'] = $filter_end_date;
    }
    
    $query .= " ORDER BY c.id DESC";
    
    $transactionsStmt = $pdo->prepare($query);
    $transactionsStmt->execute($params);
    $transactions = $transactionsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get current balance
    $balanceStmt = $pdo->prepare("SELECT balance FROM cash_on_hand ORDER BY id DESC LIMIT 1");
    $balanceStmt->execute();
    $currentBalanceData = $balanceStmt->fetch(PDO::FETCH_ASSOC);
    $current_balance = $currentBalanceData ? $currentBalanceData['balance'] : 0;
    
    // Calculate totals for filtered view
    $total_in = 0;
    $total_out = 0;
    foreach ($transactions as $transaction) {
        if ($transaction['transaction_type'] === 'in') {
            $total_in += $transaction['amount'];
        } else {
            $total_out += $transaction['amount'];
        }
    }
    
} catch(PDOException $e) {
    $transactions = [];
    $current_balance = 0;
    $total_in = 0;
    $total_out = 0;
    $_SESSION['swal_message'] = 'Error fetching transactions: ' . $e->getMessage();
    $_SESSION['swal_message_type'] = 'error';
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
        <title>Cash on Hand - OCP Construction</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" />
        <link href="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/style.min.css" rel="stylesheet" />
        <link href="css/styles.css" rel="stylesheet" />
        <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <style>
            .balance-card {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                border: none;
                border-radius: 15px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            }
            .balance-amount {
                font-size: 2.5rem;
                font-weight: bold;
            }
            .summary-card {
                transition: transform 0.2s;
                border: none;
                box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            }
            .summary-card:hover {
                transform: translateY(-2px);
                box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            }
            .income-badge {
                background-color: #28a745;
                color: white;
                padding: 5px 10px;
                border-radius: 20px;
                font-size: 0.85em;
            }
            .expense-badge {
                background-color: #dc3545;
                color: white;
                padding: 5px 10px;
                border-radius: 20px;
                font-size: 0.85em;
            }
            .table-income {
                background-color: rgba(40, 167, 69, 0.1);
            }
            .table-expense {
                background-color: rgba(220, 53, 69, 0.1);
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
                        <h1 class="mt-4">Cash on Hand</h1>
                        <ol class="breadcrumb mb-4">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Cash on Hand</li>
                        </ol>
                        
                        <!-- Current Balance Card -->
                        <div class="row mb-4">
                            <div class="col-xl-12">
                                <div class="card balance-card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h5 class="text-white-50 mb-2">Current Cash on Hand</h5>
                                                <div class="balance-amount">₱<?php echo number_format($current_balance, 2); ?></div>
                                                <small class="text-white-50">Last updated: <?php echo !empty($transactions) ? date('M d, Y h:i A', strtotime($transactions[count($transactions)-1]['created_at'])) : 'No transactions yet'; ?></small>
                                            </div>
                                            <i class="fas fa-wallet fa-4x opacity-50"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Summary Cards -->
                        <div class="row mb-4">
                            <div class="col-xl-6 col-md-6">
                                <div class="card bg-success text-white summary-card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="text-white-50 mb-1">Total Money In</h6>
                                                <h3 class="mb-0">₱<?php echo number_format($total_in, 2); ?></h3>
                                            </div>
                                            <i class="fas fa-arrow-down fa-3x opacity-50"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-6 col-md-6">
                                <div class="card bg-danger text-white summary-card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="text-white-50 mb-1">Total Money Out</h6>
                                                <h3 class="mb-0">₱<?php echo number_format($total_out, 2); ?></h3>
                                            </div>
                                            <i class="fas fa-arrow-up fa-3x opacity-50"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Filter Section -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="fas fa-filter me-1"></i>
                                Filter Transactions
                            </div>
                            <div class="card-body">
                                <form method="GET" action="" class="row g-3">
                                    <div class="col-md-3">
                                        <label for="filter_type" class="form-label">Transaction Type</label>
                                        <select class="form-select" id="filter_type" name="filter_type">
                                            <option value="">All Types</option>
                                            <option value="in" <?php echo $filter_type == 'in' ? 'selected' : ''; ?>>Money In</option>
                                            <option value="out" <?php echo $filter_type == 'out' ? 'selected' : ''; ?>>Money Out</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="start_date" class="form-label">Start Date</label>
                                        <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $filter_start_date; ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label for="end_date" class="form-label">End Date</label>
                                        <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $filter_end_date; ?>">
                                    </div>
                                    <div class="col-md-3 d-flex align-items-end">
                                        <button type="submit" class="btn btn-primary me-2">
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
                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fas fa-table me-1"></i>
                                    Transaction History
                                </div>
                                <div>
                                    <a href="cash_on_hand_report_pdf.php?<?php echo http_build_query($_GET); ?>" class="btn btn-danger me-2" target="_blank">
                                        <i class="fas fa-file-pdf me-1"></i> Generate PDF
                                    </a>
                                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTransactionModal">
                                        <i class="fas fa-plus me-1"></i> Add Transaction
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
                                            <tr class="<?php echo $transaction['transaction_type'] === 'in' ? 'table-income' : 'table-expense'; ?>">
                                                <td><?php echo date('m-d-Y', strtotime($transaction['transaction_date'])); ?></td>
                                                <td>
                                                    <?php if ($transaction['transaction_type'] === 'in'): ?>
                                                        <span class="badge income-badge">MONEY IN</span>
                                                    <?php else: ?>
                                                        <span class="badge expense-badge">MONEY OUT</span>
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
                                                    <div class="btn-group" role="group">
                                                        <form method="POST" action="" class="d-inline">
                                                            <button type="button" class="btn btn-sm btn-info view-transaction-btn" 
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

                                                        <form method="POST" action="" class="d-inline">
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

                                                        <form method="POST" action="" class="d-inline">
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
                                            <tr class="table-secondary fw-bold">
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
        <div class="modal fade" id="addTransactionModal" tabindex="-1" aria-labelledby="addTransactionModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addTransactionModalLabel">Add New Transaction</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="" id="addTransactionForm">
                        <div class="modal-body">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <select class="form-control" id="add_transaction_type" name="transaction_type" required>
                                            <option value="">Select Type</option>
                                            <option value="in">Money In (Income)</option>
                                            <option value="out">Money Out (Expense)</option>
                                        </select>
                                        <label for="add_transaction_type">Transaction Type <span class="text-danger">*</span></label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="add_amount" name="amount" 
                                               step="0.01" min="0.01" required placeholder="Amount"
                                               onkeyup="formatAmount(this)">
                                        <label for="add_amount">Amount (₱) <span class="text-danger">*</span></label>
                                        <small class="text-muted">Format: Automatically adds commas</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="date" class="form-control" id="add_transaction_date" name="transaction_date" 
                                               value="<?php echo date('Y-m-d'); ?>" required placeholder="Transaction Date">
                                        <label for="add_transaction_date">Transaction Date <span class="text-danger">*</span></label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <textarea class="form-control" id="add_description" name="description" 
                                          style="height: 100px" placeholder="Description" required></textarea>
                                <label for="add_description">Description <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
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
        <div class="modal fade" id="editTransactionModal" tabindex="-1" aria-labelledby="editTransactionModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editTransactionModalLabel">Edit Transaction</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="" id="editTransactionForm">
                        <input type="hidden" name="transaction_id" id="edit_transaction_id">
                        <div class="modal-body">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <select class="form-control" id="edit_transaction_type" name="transaction_type" required>
                                            <option value="">Select Type</option>
                                            <option value="in">Money In (Income)</option>
                                            <option value="out">Money Out (Expense)</option>
                                        </select>
                                        <label for="edit_transaction_type">Transaction Type <span class="text-danger">*</span></label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="edit_amount" name="amount" 
                                               step="0.01" min="0.01" required placeholder="Amount"
                                               onkeyup="formatAmount(this)">
                                        <label for="edit_amount">Amount (₱) <span class="text-danger">*</span></label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="date" class="form-control" id="edit_transaction_date" name="transaction_date" required>
                                        <label for="edit_transaction_date">Transaction Date <span class="text-danger">*</span></label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-floating mb-3">
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
        <div class="modal fade" id="viewTransactionModal" tabindex="-1" aria-labelledby="viewTransactionModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="viewTransactionModalLabel">Transaction Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Transaction Type:</strong>
                                <p class="text-muted" id="view_transaction_type"></p>
                            </div>
                            <div class="col-md-6">
                                <strong>Amount:</strong>
                                <p class="text-muted fw-bold" id="view_amount"></p>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Transaction Date:</strong>
                                <p class="text-muted" id="view_transaction_date"></p>
                            </div>
                            <div class="col-md-6">
                                <strong>Previous Balance:</strong>
                                <p class="text-muted" id="view_previous_balance"></p>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>New Balance:</strong>
                                <p class="text-muted fw-bold" id="view_balance"></p>
                            </div>
                            <div class="col-md-6">
                                <strong>Added By:</strong>
                                <p class="text-muted" id="view_created_by"></p>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Date Added:</strong>
                                <p class="text-muted" id="view_created_at"></p>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-12">
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
        
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>
        <script>
            // Function to format amount with commas
            function formatAmount(input) {
                // Remove all non-numeric characters except decimal point
                let value = input.value.replace(/[^\d.]/g, '');
                
                // Split into whole and decimal parts
                let parts = value.split('.');
                let wholePart = parts[0];
                let decimalPart = parts.length > 1 ? '.' + parts[1].slice(0, 2) : '';
                
                // Add commas to whole part
                if (wholePart) {
                    wholePart = wholePart.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                }
                
                // Combine whole and decimal parts
                input.value = wholePart + decimalPart;
            }

            document.addEventListener('DOMContentLoaded', function() {
                // Initialize DataTables
                const datatablesSimple = document.getElementById('datatablesSimple');
                if (datatablesSimple) {
                    new simpleDatatables.DataTable(datatablesSimple, {
                        perPage: 25,
                        labels: {
                            placeholder: "Search transactions...",
                            perPage: "{select} entries per page",
                            noRows: "No transactions found",
                            info: "Showing {start} to {end} of {rows} entries"
                        }
                    });
                }
                
                // View transaction modal handler
                const viewButtons = document.querySelectorAll('.view-transaction-btn');
                viewButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        const type = this.getAttribute('data-type');
                        const amount = parseFloat(this.getAttribute('data-amount')).toFixed(2);
                        const date = this.getAttribute('data-date');
                        const description = this.getAttribute('data-description') || 'No description provided';
                        const previousBalance = parseFloat(this.getAttribute('data-previous-balance')).toFixed(2);
                        const balance = parseFloat(this.getAttribute('data-balance')).toFixed(2);
                        const created = this.getAttribute('data-created');
                        const creator = this.getAttribute('data-creator');
                        
                        document.getElementById('view_transaction_type').innerHTML = type === 'in' ? 
                            '<span class="badge income-badge">MONEY IN</span>' : 
                            '<span class="badge expense-badge">MONEY OUT</span>';
                        document.getElementById('view_amount').textContent = '₱' + amount.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                        document.getElementById('view_transaction_date').textContent = new Date(date).toLocaleDateString('en-US', {
                            year: 'numeric',
                            month: 'long',
                            day: 'numeric'
                        });
                        document.getElementById('view_previous_balance').textContent = '₱' + previousBalance.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                        document.getElementById('view_balance').textContent = '₱' + balance.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                        document.getElementById('view_description').textContent = description;
                        document.getElementById('view_created_by').textContent = creator;
                        document.getElementById('view_created_at').textContent = new Date(created).toLocaleString('en-US', {
                            year: 'numeric',
                            month: 'long',
                            day: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit'
                        });
                        
                        new bootstrap.Modal(document.getElementById('viewTransactionModal')).show();
                    });
                });
                
                // Edit transaction modal handler
                const editButtons = document.querySelectorAll('.edit-transaction-btn');
                editButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        const id = this.getAttribute('data-id');
                        const type = this.getAttribute('data-type');
                        const amount = parseFloat(this.getAttribute('data-amount')).toFixed(2);
                        const date = this.getAttribute('data-date');
                        const description = this.getAttribute('data-description');
                        
                        document.getElementById('edit_transaction_id').value = id;
                        document.getElementById('edit_transaction_type').value = type;
                        document.getElementById('edit_amount').value = amount.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                        document.getElementById('edit_transaction_date').value = date;
                        document.getElementById('edit_description').value = description;
                    });
                });
                
                // Delete transaction handler with SweetAlert2
                const deleteButtons = document.querySelectorAll('.delete-transaction-btn');
                deleteButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        const transactionId = this.getAttribute('data-id');
                        
                        Swal.fire({
                            title: 'Are you sure?',
                            text: "You won't be able to revert this! This will affect all subsequent balances.",
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#d33',
                            cancelButtonColor: '#3085d6',
                            confirmButtonText: 'Yes, delete it!',
                            cancelButtonText: 'Cancel'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                // Set the transaction ID in the hidden form and submit
                                document.getElementById('delete_transaction_id').value = transactionId;
                                document.getElementById('deleteTransactionForm').submit();
                            }
                        });
                    });
                });
                
                // Add form submission handler to remove commas
                document.getElementById('addTransactionForm').addEventListener('submit', function(e) {
                    const amountInput = this.querySelector('input[name="amount"]');
                    if (amountInput) {
                        amountInput.value = amountInput.value.replace(/,/g, '');
                    }
                });
                
                document.getElementById('editTransactionForm').addEventListener('submit', function(e) {
                    const amountInput = this.querySelector('input[name="amount"]');
                    if (amountInput) {
                        amountInput.value = amountInput.value.replace(/,/g, '');
                    }
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
            });
        </script>
    </body>
</html>