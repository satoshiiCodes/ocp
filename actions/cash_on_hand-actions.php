<?php
/**
 * actions/cash_on_hand-actions.php
 *
 * Every action for cash_on_hand.php lives in this one file: adding, editing and
 * deleting a cash transaction.
 *
 * The page pulls this file in at the top, so it runs in the page's scope. Flash
 * messages travel in the session and the page's own block reads them back after the
 * redirect, so the messages shown to the user are unchanged. The balance checks
 * happen here, against the same tables the page's endpoint reads.
 *
 * The handler bodies below are lifted verbatim from cash_on_hand.php: the queries,
 * the balance arithmetic and the messages are unchanged.
 */

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    return;
}
if (defined('OCP_CASH_ON_HAND_ACTIONS_RAN')) {
    return;
}
define('OCP_CASH_ON_HAND_ACTIONS_RAN', true);
// ---------------------------------------------------------------- add
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

// ---------------------------------------------------------------- edit
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

// ---------------------------------------------------------------- delete
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


