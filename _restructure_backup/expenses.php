<?php
session_start();
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Database connection
require_once 'includes/db_config.php';

// Function to update cash on hand balance
function updateCashOnHand($pdo, $transaction_type, $amount, $previous_balance, $new_balance, $transaction_date, $description, $created_by) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO cash_on_hand (
                transaction_type, amount, previous_balance, balance, 
                transaction_date, description, created_by, created_at
            ) VALUES (
                :transaction_type, :amount, :previous_balance, :balance,
                :transaction_date, :description, :created_by, NOW()
            )
        ");
        
        $stmt->bindParam(':transaction_type', $transaction_type);
        $stmt->bindParam(':amount', $amount);
        $stmt->bindParam(':previous_balance', $previous_balance);
        $stmt->bindParam(':balance', $new_balance);
        $stmt->bindParam(':transaction_date', $transaction_date);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':created_by', $created_by);
        
        return $stmt->execute();
    } catch(PDOException $e) {
        error_log("Error updating cash on hand: " . $e->getMessage());
        return false;
    }
}

// Function to get current cash on hand balance
function getCurrentCashBalance($pdo) {
    try {
        $stmt = $pdo->query("SELECT balance FROM cash_on_hand ORDER BY id DESC LIMIT 1");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? floatval($result['balance']) : 0.00;
    } catch(PDOException $e) {
        error_log("Error getting cash balance: " . $e->getMessage());
        return 0.00;
    }
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

// Process form submission for adding expense
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_expense'])) {
    // Remove commas from amount before processing
    $amount = str_replace(',', '', $_POST['amount']);
    $expense_type_id = $_POST['expense_type_id'];
    $amount = floatval($amount);
    $expense_date = $_POST['expense_date'];
    $user_description = trim($_POST['description']);
    $person_name_input = isset($_POST['person_name_input']) ? trim($_POST['person_name_input']) : '';
    $employee_id = isset($_POST['employee_id']) && !empty($_POST['employee_id']) ? $_POST['employee_id'] : null;
    $ceo_id = isset($_POST['ceo_id']) && !empty($_POST['ceo_id']) ? $_POST['ceo_id'] : null;
    
    // Determine if using dropdown or manual input
    $person_selection_type = $_POST['person_selection_type'] ?? 'none';
    $person_name = null; // Only set for manual input
    
    // Get expense type name for description building
    try {
        $typeStmt = $pdo->prepare("SELECT expense_name FROM expenses_type WHERE id = :id");
        $typeStmt->bindParam(':id', $expense_type_id);
        $typeStmt->execute();
        $expense_type_data = $typeStmt->fetch(PDO::FETCH_ASSOC);
        $expense_type_name = $expense_type_data['expense_name'];
    } catch(PDOException $e) {
        $expense_type_name = '';
    }
    
    // Basic validation
    if (empty($expense_type_id) || $amount <= 0 || empty($expense_date)) {
        $_SESSION['swal_message'] = 'Please fill in all required fields and ensure amount is greater than 0.';
        $_SESSION['swal_message_type'] = 'error';
    } else {
        try {
            // Begin transaction
            $pdo->beginTransaction();
            
            // Build the full description based on expense type and person selection
            $full_description = '';
            
            if ($person_selection_type === 'employee' && !empty($employee_id)) {
                // Get employee name
                $empStmt = $pdo->prepare("SELECT firstname, lastname FROM employee WHERE id = :id");
                $empStmt->bindParam(':id', $employee_id);
                $empStmt->execute();
                $employee = $empStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($employee) {
                    $employee_name = $employee['firstname'] . ' ' . $employee['lastname'];
                    $full_description = $expense_type_name . " for Employee: " . $employee_name;
                    if (!empty($user_description)) {
                        $full_description .= " - " . $user_description;
                    }
                }
                
            } elseif ($person_selection_type === 'ceo' && !empty($ceo_id)) {
                // Get CEO name
                $ceoStmt = $pdo->prepare("SELECT firstname, middlename, lastname, suffix FROM users WHERE id = :id");
                $ceoStmt->bindParam(':id', $ceo_id);
                $ceoStmt->execute();
                $ceo = $ceoStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($ceo) {
                    // Format CEO name
                    $ceo_name = $ceo['firstname'];
                    if (!empty($ceo['middlename'])) {
                        $ceo_name .= ' ' . substr($ceo['middlename'], 0, 1) . '.';
                    }
                    $ceo_name .= ' ' . $ceo['lastname'];
                    if (!empty($ceo['suffix'])) {
                        $ceo_name .= ' ' . $ceo['suffix'];
                    }
                    
                    $full_description = $expense_type_name . " for CEO: " . $ceo_name;
                    if (!empty($user_description)) {
                        $full_description .= " - " . $user_description;
                    }
                }
                
            } elseif ($person_selection_type === 'manual' && !empty($person_name_input)) {
                $person_name = $person_name_input;
                $full_description = $expense_type_name . " for: " . $person_name_input;
                if (!empty($user_description)) {
                    $full_description .= " - " . $user_description;
                }
                
            } else {
                // For cases with no person selected
                $full_description = $expense_type_name;
                if (!empty($user_description)) {
                    $full_description .= " - " . $user_description;
                }
            }
            
            // Insert new expense
            $insertStmt = $pdo->prepare("INSERT INTO expenses (expense_type_id, amount, expense_date, description, employee_id, ceo_id, person_name, created_by) VALUES (:expense_type_id, :amount, :expense_date, :description, :employee_id, :ceo_id, :person_name, :created_by)");
            $insertStmt->bindParam(':expense_type_id', $expense_type_id);
            $insertStmt->bindParam(':amount', $amount);
            $insertStmt->bindParam(':expense_date', $expense_date);
            $insertStmt->bindParam(':description', $full_description);
            $insertStmt->bindParam(':employee_id', $employee_id);
            $insertStmt->bindParam(':ceo_id', $ceo_id);
            $insertStmt->bindParam(':person_name', $person_name);
            $insertStmt->bindParam(':created_by', $_SESSION['user_id']);
            
            if ($insertStmt->execute()) {
                // Get current cash balance
                $current_balance = getCurrentCashBalance($pdo);
                $new_balance = $current_balance - $amount;
                
                // Update cash on hand table (expense is a deduction)
                $coh_description = "Expense added: " . $full_description;
                updateCashOnHand(
                    $pdo, 
                    'out', 
                    $amount, 
                    $current_balance, 
                    $new_balance, 
                    $expense_date, 
                    $coh_description, 
                    $_SESSION['user_id']
                );
                
                $pdo->commit();
                $_SESSION['swal_message'] = 'Expense added successfully! Cash on hand updated.';
                $_SESSION['swal_message_type'] = 'success';
                
                // Redirect to refresh the page
                header("Location: ".$_SERVER['PHP_SELF']);
                exit();
            } else {
                $pdo->rollBack();
                $_SESSION['swal_message'] = 'Error adding expense. Please try again.';
                $_SESSION['swal_message_type'] = 'error';
            }
        } catch(PDOException $e) {
            $pdo->rollBack();
            $_SESSION['swal_message'] = 'Database error: ' . $e->getMessage();
            $_SESSION['swal_message_type'] = 'error';
        }
    }
}

// Process edit expense request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_expense'])) {
    $expense_id = (int)$_POST['expense_id'];
    // Remove commas from amount before processing
    $new_amount = str_replace(',', '', $_POST['amount']);
    $expense_type_id = $_POST['expense_type_id'];
    $new_amount = floatval($new_amount);
    $expense_date = $_POST['expense_date'];
    $user_description = trim($_POST['description']);
    $person_name_input = isset($_POST['person_name_input']) ? trim($_POST['person_name_input']) : '';
    $employee_id = isset($_POST['employee_id']) && !empty($_POST['employee_id']) ? $_POST['employee_id'] : null;
    $ceo_id = isset($_POST['ceo_id']) && !empty($_POST['ceo_id']) ? $_POST['ceo_id'] : null;
    
    // Determine if using dropdown or manual input
    $person_selection_type = $_POST['person_selection_type'] ?? 'none';
    $person_name = null; // Only set for manual input
    
    // Get the original expense data
    try {
        $originalStmt = $pdo->prepare("SELECT * FROM expenses WHERE id = :id");
        $originalStmt->bindParam(':id', $expense_id);
        $originalStmt->execute();
        $original_expense = $originalStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$original_expense) {
            $_SESSION['swal_message'] = 'Expense not found.';
            $_SESSION['swal_message_type'] = 'error';
            header("Location: ".$_SERVER['PHP_SELF']);
            exit();
        }
        
        $original_amount = floatval($original_expense['amount']);
    } catch(PDOException $e) {
        $_SESSION['swal_message'] = 'Error fetching original expense: ' . $e->getMessage();
        $_SESSION['swal_message_type'] = 'error';
        header("Location: ".$_SERVER['PHP_SELF']);
        exit();
    }
    
    // Get expense type name for description building
    try {
        $typeStmt = $pdo->prepare("SELECT expense_name FROM expenses_type WHERE id = :id");
        $typeStmt->bindParam(':id', $expense_type_id);
        $typeStmt->execute();
        $expense_type_data = $typeStmt->fetch(PDO::FETCH_ASSOC);
        $expense_type_name = $expense_type_data['expense_name'];
    } catch(PDOException $e) {
        $expense_type_name = '';
    }
    
    // Basic validation
    if (empty($expense_type_id) || $new_amount <= 0 || empty($expense_date)) {
        $_SESSION['swal_message'] = 'Please fill in all required fields and ensure amount is greater than 0.';
        $_SESSION['swal_message_type'] = 'error';
    } else {
        try {
            // Begin transaction
            $pdo->beginTransaction();
            
            // Build the full description based on expense type and person selection
            $full_description = '';
            
            if ($person_selection_type === 'employee' && !empty($employee_id)) {
                // Get employee name
                $empStmt = $pdo->prepare("SELECT firstname, lastname FROM employee WHERE id = :id");
                $empStmt->bindParam(':id', $employee_id);
                $empStmt->execute();
                $employee = $empStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($employee) {
                    $employee_name = $employee['firstname'] . ' ' . $employee['lastname'];
                    $full_description = $expense_type_name . " for Employee: " . $employee_name;
                    if (!empty($user_description)) {
                        $full_description .= " - " . $user_description;
                    }
                }
                
            } elseif ($person_selection_type === 'ceo' && !empty($ceo_id)) {
                // Get CEO name
                $ceoStmt = $pdo->prepare("SELECT firstname, middlename, lastname, suffix FROM users WHERE id = :id");
                $ceoStmt->bindParam(':id', $ceo_id);
                $ceoStmt->execute();
                $ceo = $ceoStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($ceo) {
                    // Format CEO name
                    $ceo_name = $ceo['firstname'];
                    if (!empty($ceo['middlename'])) {
                        $ceo_name .= ' ' . substr($ceo['middlename'], 0, 1) . '.';
                    }
                    $ceo_name .= ' ' . $ceo['lastname'];
                    if (!empty($ceo['suffix'])) {
                        $ceo_name .= ' ' . $ceo['suffix'];
                    }
                    
                    $full_description = $expense_type_name . " for CEO: " . $ceo_name;
                    if (!empty($user_description)) {
                        $full_description .= " - " . $user_description;
                    }
                }
                
            } elseif ($person_selection_type === 'manual' && !empty($person_name_input)) {
                $person_name = $person_name_input;
                $full_description = $expense_type_name . " for: " . $person_name_input;
                if (!empty($user_description)) {
                    $full_description .= " - " . $user_description;
                }
                
            } else {
                // For cases with no person selected
                $full_description = $expense_type_name;
                if (!empty($user_description)) {
                    $full_description .= " - " . $user_description;
                }
            }
            
            // Update expense
            $updateStmt = $pdo->prepare("UPDATE expenses SET expense_type_id = :expense_type_id, amount = :amount, expense_date = :expense_date, description = :description, employee_id = :employee_id, ceo_id = :ceo_id, person_name = :person_name WHERE id = :id");
            $updateStmt->bindParam(':expense_type_id', $expense_type_id);
            $updateStmt->bindParam(':amount', $new_amount);
            $updateStmt->bindParam(':expense_date', $expense_date);
            $updateStmt->bindParam(':description', $full_description);
            $updateStmt->bindParam(':employee_id', $employee_id);
            $updateStmt->bindParam(':ceo_id', $ceo_id);
            $updateStmt->bindParam(':person_name', $person_name);
            $updateStmt->bindParam(':id', $expense_id);
            
            if ($updateStmt->execute()) {
                // Get current cash balance
                $current_balance = getCurrentCashBalance($pdo);
                
                // Calculate the difference in amount
                $amount_difference = $new_amount - $original_amount;
                
                // If amount changed, update cash on hand
                if ($amount_difference != 0) {
                    $new_balance = $current_balance - $amount_difference;
                    
                    // Determine transaction type based on difference
                    $transaction_type = $amount_difference > 0 ? 'out' : 'in';
                    $abs_difference = abs($amount_difference);
                    
                    // Create description for cash on hand entry
                    if ($amount_difference > 0) {
                        $coh_description = "Expense increased by ₱" . number_format($abs_difference, 2) . " (Edit): " . $full_description;
                    } else {
                        $coh_description = "Expense decreased by ₱" . number_format($abs_difference, 2) . " (Edit): " . $full_description;
                    }
                    
                    updateCashOnHand(
                        $pdo, 
                        $transaction_type, 
                        $abs_difference, 
                        $current_balance, 
                        $new_balance, 
                        $expense_date, 
                        $coh_description, 
                        $_SESSION['user_id']
                    );
                }
                
                $pdo->commit();
                $_SESSION['swal_message'] = 'Expense updated successfully! Cash on hand adjusted.';
                $_SESSION['swal_message_type'] = 'success';
                
                // Redirect to refresh the page
                header("Location: ".$_SERVER['PHP_SELF']);
                exit();
            } else {
                $pdo->rollBack();
                $_SESSION['swal_message'] = 'Error updating expense. Please try again.';
                $_SESSION['swal_message_type'] = 'error';
            }
        } catch(PDOException $e) {
            $pdo->rollBack();
            $_SESSION['swal_message'] = 'Database error: ' . $e->getMessage();
            $_SESSION['swal_message_type'] = 'error';
        }
    }
}

// Process delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_expense'])) {
    $expense_id = (int)$_POST['expense_id'];
    
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        // Get the expense data before deleting
        $expenseStmt = $pdo->prepare("SELECT * FROM expenses WHERE id = :id");
        $expenseStmt->bindParam(':id', $expense_id);
        $expenseStmt->execute();
        $expense = $expenseStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$expense) {
            $_SESSION['swal_message'] = 'Expense not found.';
            $_SESSION['swal_message_type'] = 'error';
            header("Location: ".$_SERVER['PHP_SELF']);
            exit();
        }
        
        $amount = floatval($expense['amount']);
        $expense_date = $expense['expense_date'];
        $description = $expense['description'];
        
        // Delete the expense
        $deleteStmt = $pdo->prepare("DELETE FROM expenses WHERE id = :id");
        $deleteStmt->bindParam(':id', $expense_id);
        
        if ($deleteStmt->execute()) {
            // Get current cash balance
            $current_balance = getCurrentCashBalance($pdo);
            $new_balance = $current_balance + $amount; // Add back the amount when deleting
            
            // Update cash on hand table (deleting expense adds money back)
            $coh_description = "Expense deleted (reversal): " . $description;
            updateCashOnHand(
                $pdo, 
                'in', 
                $amount, 
                $current_balance, 
                $new_balance, 
                $expense_date, 
                $coh_description, 
                $_SESSION['user_id']
            );
            
            $pdo->commit();
            $_SESSION['swal_message'] = 'Expense deleted successfully! Cash on hand updated.';
            $_SESSION['swal_message_type'] = 'success';
        } else {
            $pdo->rollBack();
            $_SESSION['swal_message'] = 'Error deleting expense.';
            $_SESSION['swal_message_type'] = 'error';
        }
        
        header("Location: ".$_SERVER['PHP_SELF']);
        exit();
    } catch(PDOException $e) {
        $pdo->rollBack();
        $_SESSION['swal_message'] = 'Database error: ' . $e->getMessage();
        $_SESSION['swal_message_type'] = 'error';
    }
}

// Get all expense types from database
try {
    $expenseTypesStmt = $pdo->prepare("SELECT id, expense_name, description FROM expenses_type ORDER BY expense_name");
    $expenseTypesStmt->execute();
    $expense_types_from_db = $expenseTypesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $expense_types_from_db = [];
}

// Get all expenses from database
try {
    // Get filter parameters
    $filter_type = isset($_GET['filter_type']) ? $_GET['filter_type'] : '';
    $filter_start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
    $filter_end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
    
    $query = "
        SELECT e.*, 
               u.firstname as user_firstname, u.lastname as user_lastname,
               emp.firstname as emp_firstname, emp.lastname as emp_lastname,
               ceo.firstname as ceo_firstname, ceo.middlename as ceo_middlename, ceo.lastname as ceo_lastname, ceo.suffix as ceo_suffix,
               et.expense_name as expense_type_name, et.description as expense_type_description
        FROM expenses e 
        LEFT JOIN users u ON e.created_by = u.id 
        LEFT JOIN employee emp ON e.employee_id = emp.id
        LEFT JOIN users ceo ON e.ceo_id = ceo.id
        LEFT JOIN expenses_type et ON e.expense_type_id = et.id
        WHERE 1=1
    ";
    
    $params = [];
    
    if (!empty($filter_type)) {
        $query .= " AND e.expense_type_id = :expense_type_id";
        $params[':expense_type_id'] = $filter_type;
    }
    
    if (!empty($filter_start_date)) {
        $query .= " AND DATE(e.expense_date) >= :start_date";
        $params[':start_date'] = $filter_start_date;
    }
    
    if (!empty($filter_end_date)) {
        $query .= " AND DATE(e.expense_date) <= :end_date";
        $params[':end_date'] = $filter_end_date;
    }
    
    $query .= " ORDER BY e.expense_date DESC, e.created_at DESC";
    
    $expensesStmt = $pdo->prepare($query);
    $expensesStmt->execute($params);
    $expenses = $expensesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate total amount
    $total_amount = 0;
    foreach ($expenses as $expense) {
        $total_amount += $expense['amount'];
    }
    
} catch(PDOException $e) {
    $expenses = [];
    $total_amount = 0;
    $_SESSION['swal_message'] = 'Error fetching expenses: ' . $e->getMessage();
    $_SESSION['swal_message_type'] = 'error';
}

// Get current cash on hand balance
$current_cash_balance = getCurrentCashBalance($pdo);

// Get all employees for dropdown
try {
    $employeesStmt = $pdo->prepare("
        SELECT id, firstname, middlename, lastname, suffix 
        FROM employee 
        WHERE status = 'active' 
        ORDER BY lastname, firstname
    ");
    $employeesStmt->execute();
    $employees = $employeesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $employees = [];
}

// Get all users with account type 'Admin' and position 'CEO' for CEO dropdown
try {
    $ceoUsersStmt = $pdo->prepare("
        SELECT id, firstname, middlename, lastname, suffix 
        FROM users 
        WHERE accounttype = 'Admin' AND position = 'CEO'
        ORDER BY lastname, firstname
    ");
    $ceoUsersStmt->execute();
    $ceo_users = $ceoUsersStmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $ceo_users = [];
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
        <title>Expenses - OCP Construction</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" />
        <link href="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/style.min.css" rel="stylesheet" />
        <link href="css/styles.css" rel="stylesheet" />
        <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <style>
            .btn-group .btn:last-child {
                margin-right: 5px;
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
            .total-amount {
                font-size: 1.5rem;
                font-weight: bold;
                color: #28a745;
            }
            .employee-badge {
                background-color: #17a2b8;
                color: white;
                padding: 2px 8px;
                border-radius: 4px;
                font-size: 0.85em;
                margin-left: 5px;
            }
            .ceo-badge {
                background-color: #6f42c1;
                color: white;
                padding: 2px 8px;
                border-radius: 4px;
                font-size: 0.85em;
                margin-left: 5px;
            }
            .person-name-badge {
                background-color: #28a745;
                color: white;
                padding: 2px 8px;
                border-radius: 4px;
                font-size: 0.85em;
                margin-left: 5px;
            }
            /* Style for radio group in one row */
            .radio-group-row {
                display: flex;
                flex-wrap: wrap;
                gap: 20px;
                align-items: center;
                margin-top: 5px;
            }
            .radio-group-row .form-check {
                margin-right: 15px;
                margin-bottom: 0;
            }
            .cash-balance {
                font-size: 1.2rem;
                font-weight: bold;
                color: #007bff;
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
                        <h1 class="mt-4">Expenses</h1>
                        <ol class="breadcrumb mb-4">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Expenses</li>
                        </ol>
                        
                        <!-- Summary Cards -->
                        <div class="row mb-4">
                            <div class="col-xl-4 col-md-4">
                                <div class="card bg-primary text-white summary-card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="text-white-50 mb-1">Total Expenses</h6>
                                                <h3 class="mb-0">₱<?php echo number_format($total_amount, 2); ?></h3>
                                            </div>
                                            <i class="fas fa-money-bill-wave fa-3x opacity-50"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-4 col-md-4">
                                <div class="card bg-success text-white summary-card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="text-white-50 mb-1">Total Entries</h6>
                                                <h3 class="mb-0"><?php echo count($expenses); ?></h3>
                                            </div>
                                            <i class="fas fa-list fa-3x opacity-50"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-4 col-md-4">
                                <div class="card bg-info text-white summary-card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="text-white-50 mb-1">Current Cash on Hand</h6>
                                                <h3 class="mb-0">₱<?php echo number_format($current_cash_balance, 2); ?></h3>
                                            </div>
                                            <i class="fas fa-wallet fa-3x opacity-50"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Filter Section -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="fas fa-filter me-1"></i>
                                Filter Expenses
                            </div>
                            <div class="card-body">
                                <form method="GET" action="" class="row g-3">
                                    <div class="col-md-3">
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
                                        <a href="expenses.php" class="btn btn-warning">
                                            <i class="fas fa-times"></i> Clear
                                        </a>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Expenses Table -->
                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fas fa-table me-1"></i>
                                    Expenses List
                                </div>
                                <div>
                                    <a href="expenses_report_pdf.php?<?php echo http_build_query($_GET); ?>" class="btn btn-danger me-2" target="_blank">
                                        <i class="fas fa-file-pdf me-1"></i> Generate PDF
                                    </a>
                                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
                                        <i class="fas fa-plus me-1"></i> Add Expense
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
                                                        echo '<span class="badge person-name-badge">' . htmlspecialchars($expense['person_name']) . '</span>';
                                                    } elseif (!empty($expense['emp_firstname'])) {
                                                        echo '<span class="badge employee-badge">' . 
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
                                                        echo '<span class="badge ceo-badge">' . 
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
                                                    <div class="btn-group" role="group">
                                                        <form method="POST" action="" class="d-inline">
                                                            <button type="button" class="btn btn-sm btn-info view-expense-btn" 
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

                                                        <form method="POST" action="" class="d-inline">
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

                                                        <form method="POST" action="" class="d-inline">
                                                            <button type="button" class="btn btn-sm btn-danger delete-expense-btn" 
                                                                    data-id="<?php echo $expense['id']; ?>">
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
                                                <td colspan="2" class="text-end">Total:</td>
                                                <td class="text-end">₱<?php echo number_format($total_amount, 2); ?></td>
                                                <td colspan="4"></td>
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
        <div class="modal fade" id="addExpenseModal" tabindex="-1" aria-labelledby="addExpenseModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addExpenseModalLabel">Add New Expense</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="" id="addExpenseForm">
                        <div class="modal-body">
                            <div class="form-floating mb-3">
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
                            <div class="mb-3" id="add_person_selection_container" style="display: none;">
                                <label class="form-label d-block">Person Selection Method <span class="text-danger">*</span></label>
                                <div class="radio-group-row">
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
                            <div class="form-floating mb-3" id="add_employee_field" style="display: none;">
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
                            <div class="form-floating mb-3" id="add_ceo_field" style="display: none;">
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
                            <div class="form-floating mb-3" id="add_person_name_field" style="display: none;">
                                <input type="text" class="form-control" id="add_person_name_input" name="person_name_input" placeholder="Enter person name">
                                <label for="add_person_name_input">Person Name</label>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="add_amount" name="amount" 
                                       step="0.01" min="0.01" required placeholder="Amount"
                                       onkeyup="formatAmount(this)">
                                <label for="add_amount">Amount (₱) <span class="text-danger">*</span></label>
                                <small class="text-muted">Format: Automatically adds commas (e.g., 1,000.00)</small>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <input type="date" class="form-control" id="add_expense_date" name="expense_date" 
                                       value="<?php echo date('Y-m-d'); ?>" required placeholder="Expense Date">
                                <label for="add_expense_date">Expense Date <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <textarea class="form-control" id="add_description" name="description" 
                                          style="height: 100px" placeholder="Description"></textarea>
                                <label for="add_description">Notes</label>
                            </div>
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
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
        <div class="modal fade" id="editExpenseModal" tabindex="-1" aria-labelledby="editExpenseModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editExpenseModalLabel">Edit Expense</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="" id="editExpenseForm">
                        <input type="hidden" name="expense_id" id="edit_expense_id">
                        <div class="modal-body">
                            <div class="form-floating mb-3">
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
                            <div class="mb-3" id="edit_person_selection_container" style="display: none;">
                                <label class="form-label d-block">Person Selection Method <span class="text-danger">*</span></label>
                                <div class="radio-group-row">
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
                            <div class="form-floating mb-3" id="edit_employee_field" style="display: none;">
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
                            <div class="form-floating mb-3" id="edit_ceo_field" style="display: none;">
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
                            <div class="form-floating mb-3" id="edit_person_name_field" style="display: none;">
                                <input type="text" class="form-control" id="edit_person_name_input" name="person_name_input" placeholder="Enter person name">
                                <label for="edit_person_name_input">Person Name</label>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="edit_amount" name="amount" 
                                       step="0.01" min="0.01" required placeholder="Amount"
                                       onkeyup="formatAmount(this)">
                                <label for="edit_amount">Amount (₱) <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <input type="date" class="form-control" id="edit_expense_date" name="expense_date" required>
                                <label for="edit_expense_date">Expense Date <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <textarea class="form-control" id="edit_description" name="description" 
                                          style="height: 100px" placeholder="Additional Notes"></textarea>
                                <label for="edit_description">Additional Notes</label>
                            </div>
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
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
        <div class="modal fade" id="viewExpenseModal" tabindex="-1" aria-labelledby="viewExpenseModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="viewExpenseModalLabel">Expense Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Expense Type:</strong>
                                <p class="text-muted" id="view_expense_type"></p>
                            </div>
                            <div class="col-md-6">
                                <strong>Amount:</strong>
                                <p class="text-muted fw-bold text-success" id="view_amount"></p>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Expense Date:</strong>
                                <p class="text-muted" id="view_expense_date"></p>
                            </div>
                            <div class="col-md-6">
                                <strong>Person:</strong>
                                <p class="text-muted" id="view_person"></p>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Added By:</strong>
                                <p class="text-muted" id="view_created_by"></p>
                            </div>
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
        <form method="POST" action="" id="deleteExpenseForm" style="display: none;">
            <input type="hidden" name="expense_id" id="delete_expense_id">
            <input type="hidden" name="delete_expense" value="1">
        </form>
        
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>
        <script>
            // Function to toggle fields based on expense type for Add Modal
            function toggleAddFields() {
                const select = document.getElementById('add_expense_type_id');
                const selectedOption = select.options[select.selectedIndex];
                const expenseTypeName = selectedOption ? selectedOption.getAttribute('data-name') : '';
                
                const personSelectionContainer = document.getElementById('add_person_selection_container');
                
                // Show person selection container for all expense types (except when empty)
                if (select.value) {
                    personSelectionContainer.style.display = 'block';
                } else {
                    personSelectionContainer.style.display = 'none';
                    // Hide all person fields
                    document.getElementById('add_employee_field').style.display = 'none';
                    document.getElementById('add_ceo_field').style.display = 'none';
                    document.getElementById('add_person_name_field').style.display = 'none';
                }
                
                // Reset radio buttons
                document.getElementById('add_person_selection_none').checked = true;
                toggleAddPersonFields();
            }

            // Function to toggle person fields based on selection method for Add Modal
            function toggleAddPersonFields() {
                const selectedMethod = document.querySelector('input[name="person_selection_type"]:checked')?.value || 'none';
                
                const employeeField = document.getElementById('add_employee_field');
                const ceoField = document.getElementById('add_ceo_field');
                const personNameField = document.getElementById('add_person_name_field');
                
                // Hide all fields first
                employeeField.style.display = 'none';
                ceoField.style.display = 'none';
                personNameField.style.display = 'none';
                
                // Remove required attributes
                document.getElementById('add_employee_id').removeAttribute('required');
                document.getElementById('add_ceo_id').removeAttribute('required');
                document.getElementById('add_person_name_input').removeAttribute('required');
                
                // Show appropriate field based on selection
                if (selectedMethod === 'employee') {
                    employeeField.style.display = 'block';
                    document.getElementById('add_employee_id').setAttribute('required', 'required');
                } else if (selectedMethod === 'ceo') {
                    ceoField.style.display = 'block';
                    document.getElementById('add_ceo_id').setAttribute('required', 'required');
                } else if (selectedMethod === 'manual') {
                    personNameField.style.display = 'block';
                    document.getElementById('add_person_name_input').setAttribute('required', 'required');
                }
            }

            // Function to toggle fields based on expense type for Edit Modal
            function toggleEditFields() {
                const select = document.getElementById('edit_expense_type_id');
                const selectedOption = select.options[select.selectedIndex];
                const expenseTypeName = selectedOption ? selectedOption.getAttribute('data-name') : '';
                
                const personSelectionContainer = document.getElementById('edit_person_selection_container');
                
                // Show person selection container for all expense types (except when empty)
                if (select.value) {
                    personSelectionContainer.style.display = 'block';
                } else {
                    personSelectionContainer.style.display = 'none';
                    // Hide all person fields
                    document.getElementById('edit_employee_field').style.display = 'none';
                    document.getElementById('edit_ceo_field').style.display = 'none';
                    document.getElementById('edit_person_name_field').style.display = 'none';
                }
            }

            // Function to toggle person fields based on selection method for Edit Modal
            function toggleEditPersonFields() {
                const selectedMethod = document.querySelector('input[name="person_selection_type"]:checked')?.value || 'none';
                
                const employeeField = document.getElementById('edit_employee_field');
                const ceoField = document.getElementById('edit_ceo_field');
                const personNameField = document.getElementById('edit_person_name_field');
                
                // Hide all fields first
                employeeField.style.display = 'none';
                ceoField.style.display = 'none';
                personNameField.style.display = 'none';
                
                // Remove required attributes
                document.getElementById('edit_employee_id').removeAttribute('required');
                document.getElementById('edit_ceo_id').removeAttribute('required');
                document.getElementById('edit_person_name_input').removeAttribute('required');
                
                // Show appropriate field based on selection
                if (selectedMethod === 'employee') {
                    employeeField.style.display = 'block';
                    document.getElementById('edit_employee_id').setAttribute('required', 'required');
                } else if (selectedMethod === 'ceo') {
                    ceoField.style.display = 'block';
                    document.getElementById('edit_ceo_id').setAttribute('required', 'required');
                } else if (selectedMethod === 'manual') {
                    personNameField.style.display = 'block';
                    document.getElementById('edit_person_name_input').setAttribute('required', 'required');
                }
            }

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

            // Function to extract user-editable part from full description
            function extractUserDescription(fullDescription, expenseTypeName) {
                if (!fullDescription) return '';
                
                // Try to find the last part after the last " - "
                const lastDashIndex = fullDescription.lastIndexOf(' - ');
                if (lastDashIndex !== -1) {
                    return fullDescription.substring(lastDashIndex + 3);
                }
                return ''; // If no dash, user part is empty
            }

            // Function to determine person selection type from existing data
            function determinePersonSelectionType(expenseData) {
                if (expenseData.employeeId && expenseData.employeeId !== '' && expenseData.employeeId !== 'null') {
                    return 'employee';
                } else if (expenseData.ceoId && expenseData.ceoId !== '' && expenseData.ceoId !== 'null') {
                    return 'ceo';
                } else if (expenseData.personName && expenseData.personName !== '' && expenseData.personName !== 'null') {
                    return 'manual';
                } else {
                    return 'none';
                }
            }

            document.addEventListener('DOMContentLoaded', function() {
                // Initialize DataTables
                const datatablesSimple = document.getElementById('datatablesSimple');
                if (datatablesSimple) {
                    new simpleDatatables.DataTable(datatablesSimple, {
                        perPage: 25,
                        labels: {
                            placeholder: "Search expenses...",
                            perPage: "{select} entries per page",
                            noRows: "No expenses found",
                            info: "Showing {start} to {end} of {rows} entries"
                        }
                    });
                }
                
                // View expense modal handler
                const viewButtons = document.querySelectorAll('.view-expense-btn');
                viewButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        const type = this.getAttribute('data-type');
                        const amount = parseFloat(this.getAttribute('data-amount')).toFixed(2);
                        const date = this.getAttribute('data-date');
                        const description = this.getAttribute('data-description') || 'No description provided';
                        const personName = this.getAttribute('data-person-name');
                        const employee = this.getAttribute('data-employee') || '';
                        const created = this.getAttribute('data-created');
                        const creator = this.getAttribute('data-creator');
                        
                        // Determine which person to display
                        let personDisplay = '—';
                        if (personName && personName !== '') {
                            personDisplay = personName;
                        } else if (employee && employee !== '') {
                            personDisplay = employee;
                        }
                        
                        document.getElementById('view_expense_type').textContent = type;
                        document.getElementById('view_amount').textContent = '₱' + amount.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                        document.getElementById('view_expense_date').textContent = new Date(date).toLocaleDateString('en-US', {
                            year: 'numeric',
                            month: 'long',
                            day: 'numeric'
                        });
                        document.getElementById('view_person').textContent = personDisplay;
                        document.getElementById('view_description').textContent = description;
                        document.getElementById('view_created_by').textContent = creator;
                        document.getElementById('view_created_at').textContent = new Date(created).toLocaleString('en-US', {
                            year: 'numeric',
                            month: 'long',
                            day: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit'
                        });
                        
                        new bootstrap.Modal(document.getElementById('viewExpenseModal')).show();
                    });
                });
                
                // Edit expense modal handler
                const editButtons = document.querySelectorAll('.edit-expense-btn');
                editButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        const id = this.getAttribute('data-id');
                        const typeId = this.getAttribute('data-type-id');
                        const typeName = this.getAttribute('data-type-name');
                        const amount = parseFloat(this.getAttribute('data-amount')).toFixed(2);
                        const date = this.getAttribute('data-date');
                        const fullDescription = this.getAttribute('data-full-description');
                        const personName = this.getAttribute('data-person-name');
                        const employeeId = this.getAttribute('data-employee-id');
                        const ceoId = this.getAttribute('data-ceo-id');
                        
                        // Set the expense type select value
                        const typeSelect = document.getElementById('edit_expense_type_id');
                        typeSelect.value = typeId;
                        
                        // Extract only the user-editable part of the description
                        const userDescription = extractUserDescription(fullDescription, typeName);
                        
                        document.getElementById('edit_expense_id').value = id;
                        document.getElementById('edit_amount').value = amount.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                        document.getElementById('edit_expense_date').value = date;
                        document.getElementById('edit_description').value = userDescription;
                        
                        // Determine person selection type
                        const expenseData = {
                            employeeId: employeeId,
                            ceoId: ceoId,
                            personName: personName
                        };
                        const selectionType = determinePersonSelectionType(expenseData);
                        
                        // Set the radio button
                        const radioButtons = document.querySelectorAll('input[name="person_selection_type"]');
                        radioButtons.forEach(radio => {
                            if (radio.value === selectionType) {
                                radio.checked = true;
                            }
                        });
                        
                        // Clear all fields first
                        document.getElementById('edit_employee_id').value = '';
                        document.getElementById('edit_ceo_id').value = '';
                        document.getElementById('edit_person_name_input').value = '';
                        
                        // Set values in appropriate fields
                        if (selectionType === 'employee' && employeeId) {
                            document.getElementById('edit_employee_id').value = employeeId;
                        } else if (selectionType === 'ceo' && ceoId) {
                            document.getElementById('edit_ceo_id').value = ceoId;
                        } else if (selectionType === 'manual' && personName) {
                            document.getElementById('edit_person_name_input').value = personName;
                        }
                        
                        // Trigger change event for the type select to ensure proper field display
                        const event = new Event('change');
                        typeSelect.dispatchEvent(event);
                        
                        // Trigger person field display
                        toggleEditPersonFields();
                    });
                });
                
                // Delete expense handler with SweetAlert2
                const deleteButtons = document.querySelectorAll('.delete-expense-btn');
                deleteButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        const expenseId = this.getAttribute('data-id');
                        
                        Swal.fire({
                            title: 'Are you sure?',
                            text: "You won't be able to revert this! This will also update the cash on hand balance.",
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#d33',
                            cancelButtonColor: '#3085d6',
                            confirmButtonText: 'Yes, delete it!',
                            cancelButtonText: 'Cancel'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                // Set the expense ID in the hidden form and submit
                                document.getElementById('delete_expense_id').value = expenseId;
                                document.getElementById('deleteExpenseForm').submit();
                            }
                        });
                    });
                });
                
                // Add form submission handler to remove commas
                document.getElementById('addExpenseForm').addEventListener('submit', function(e) {
                    const amountInput = this.querySelector('input[name="amount"]');
                    if (amountInput) {
                        amountInput.value = amountInput.value.replace(/,/g, '');
                    }
                    
                    // Ensure person_selection_type is set
                    const selectedMethod = document.querySelector('input[name="person_selection_type"]:checked');
                    if (!selectedMethod) {
                        e.preventDefault();
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Please select a person selection method.'
                        });
                        return false;
                    }
                });
                
                document.getElementById('editExpenseForm').addEventListener('submit', function(e) {
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