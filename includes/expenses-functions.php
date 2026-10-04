<?php
/**
 * includes/expenses-functions.php
 *
 * The two helpers expenses.php and its actions file share: updateCashOnHand(),
 * which writes a cash movement alongside an expense, and getCurrentCashBalance(),
 * which reads the running balance.
 *
 * They live in their own file because both entry points need them. The handlers call
 * both; the page calls getCurrentCashBalance() while rendering its summary. They must
 * therefore be loaded before whichever runs first, which is why
 * actions/expenses-actions.php and api/expenses-endpoint.php both require this file.
 *
 * The bodies below are lifted verbatim from expenses.php; nothing changed.
 */

if (defined('OCP_EXPENSES_FUNCTIONS_LOADED')) {
    return;
}
define('OCP_EXPENSES_FUNCTIONS_LOADED', true);
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
