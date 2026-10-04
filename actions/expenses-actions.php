<?php
/**
 * actions/expenses-actions.php
 *
 * Every action for expenses.php lives in this one file: adding an expense, editing
 * one, and deleting one.
 *
 * The page pulls this file in at the top, so it runs in the page's scope. Each
 * branch reports through the session and then redirects or re-renders, so the
 * messages the page shows are unchanged. Editing and deleting also move the cash
 * balance, which they do through the shared helper below.
 *
 * The helpers this calls live in includes/expenses-functions.php, required here so
 * they are defined whichever entry point runs first.
 *
 * The block below is lifted verbatim from expenses.php: the queries, the balance
 * arithmetic and the messages are unchanged.
 */

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    return;
}
if (defined('OCP_EXPENSES_ACTIONS_RAN')) {
    return;
}
define('OCP_EXPENSES_ACTIONS_RAN', true);

require_once __DIR__ . '/../includes/expenses-functions.php';
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
                $new_expense_id = (int) $pdo->lastInsertId();

                // An expense on a type that needs approval starts as pending and is signed by
                // three roles in order; everything else is not_required. The column default is
                // not_required, so only the pending case is written here.
                $typeCheck = $pdo->prepare("SELECT approval_required FROM expenses_type WHERE id = :id");
                $typeCheck->bindParam(':id', $expense_type_id);
                $typeCheck->execute();
                if ((int) $typeCheck->fetchColumn() === 1) {
                    $pdo->prepare("UPDATE expenses SET approval_status = 'pending' WHERE id = :id")
                        ->execute([':id' => $new_expense_id]);
                }

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

// ---------------------------------------------------------------- approve / sign
//
// An expense whose type requires approval is signed by three roles, in order:
//
//   step 1  reviewed      Admin + Admin dept + Accounting
//   step 2  prepared      Admin + Admin dept + CEO
//   step 3  acknowledged  Admin + Admin dept + CEO
//
// Each step unlocks only once the one before it is signed, and the signature is stored as the
// pad's data URL - the same shape gasoline_purchase_orders keeps. When the last step is signed
// the expense becomes 'approved' and is counted in reports again.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_expense'])) {
    $expense_id = isset($_POST['expense_id']) ? (int) $_POST['expense_id'] : 0;
    $signature = trim($_POST['signature_data'] ?? '');

    // The rule for each step, and the column it writes. Kept in one place so the page and the
    // handler cannot disagree about who may sign what.
    //
    // The step keys are the column prefixes, and the signing order is the order they appear here:
    // Accounting first, then the CEO twice.
    //
    // The label is the name the step is shown under. The first step is "Prepared by" - the line the
    // Accounting officer signs - not "Reviewed by"; the labels were crossed here, which put the wrong
    // name on the first two steps everywhere they were read from this map.
    $ocp_steps = [
        'reviewed' => [
            'label' => 'Prepared by',
            'by' => 'reviewed_by', 'sig' => 'reviewed_signature', 'at' => 'reviewed_at',
            'matches' => static fn(array $u): bool => $u['accounttype'] === 'Admin'
                && $u['department'] === 'Admin' && $u['position'] === 'Accounting',
        ],
        'prepared' => [
            'label' => 'Reviewed by',
            'by' => 'prepared_by', 'sig' => 'prepared_signature', 'at' => 'prepared_at',
            'matches' => static fn(array $u): bool => $u['accounttype'] === 'Admin'
                && $u['department'] === 'Admin' && $u['position'] === 'CEO',
        ],
        'acknowledged' => [
            'label' => 'Acknowledged by',
            'by' => 'acknowledged_by', 'sig' => 'acknowledged_signature', 'at' => 'acknowledged_at',
            'matches' => static fn(array $u): bool => $u['accounttype'] === 'Admin'
                && $u['department'] === 'Admin' && $u['position'] === 'CEO',
        ],
    ];

    try {
        $meStmt = $pdo->prepare("SELECT accounttype, department, position FROM users WHERE id = :id");
        $meStmt->bindParam(':id', $_SESSION['user_id']);
        $meStmt->execute();
        $me = $meStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $expStmt = $pdo->prepare("SELECT id, approval_status, reviewed_at, prepared_at FROM expenses WHERE id = :id");
        $expStmt->bindParam(':id', $expense_id);
        $expStmt->execute();
        $expense_row = $expStmt->fetch(PDO::FETCH_ASSOC);

        if (!$expense_row) {
            $_SESSION['swal_message'] = 'That expense no longer exists.';
            $_SESSION['swal_message_type'] = 'error';
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }

        if ($signature === '' || strpos($signature, 'data:image') !== 0) {
            $_SESSION['swal_message'] = 'A signature is required before this expense can be signed.';
            $_SESSION['swal_message_type'] = 'error';
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }

        // Which step this user is signing, and whether the steps before it are done.
        //
        // The step is the first one this user matches that is still unsigned - not simply the
        // first they match. A CEO matches both the Prepared and the Acknowledged rule, so picking
        // the first match would leave the third step impossible to sign once the second was done.
        //
        // Every earlier step must already be signed, which is what makes the order strict.
        $step = null;
        $missing_before = null;
        $order = array_keys($ocp_steps);

        foreach ($order as $key) {
            if (!$ocp_steps[$key]['matches']($me)) {
                continue;
            }
            // Already signed by someone: this is not the step to act on.
            if (!empty($expense_row[$ocp_steps[$key]['at']])) {
                continue;
            }

            // The first earlier step that is still unsigned blocks it.
            foreach (array_slice($order, 0, array_search($key, $order, true)) as $prev) {
                if (empty($expense_row[$ocp_steps[$prev]['at']])) {
                    $missing_before = $ocp_steps[$prev]['label'];
                    break 2;
                }
            }

            $step = $key;
            break;
        }

        if ($step === null && $missing_before === null) {
            // Every step they could sign is already signed.
            $_SESSION['swal_message'] = 'There is nothing left for you to sign on this expense.';
            $_SESSION['swal_message_type'] = 'error';
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }

        if ($step === null) {
            $_SESSION['swal_message'] = 'Your account is not one of the roles that signs this expense.';
            $_SESSION['swal_message_type'] = 'error';
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }

        if ($missing_before !== null) {
            $_SESSION['swal_message'] = $missing_before . ' must sign first.';
            $_SESSION['swal_message_type'] = 'error';
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }

        // Sign it.
        $def = $ocp_steps[$step];
        $upd = $pdo->prepare("UPDATE expenses
                                 SET {$def['by']} = :by,
                                     {$def['sig']} = :sig,
                                     {$def['at']} = NOW()
                               WHERE id = :id");
        $upd->bindParam(':by', $_SESSION['user_id'], PDO::PARAM_INT);
        $upd->bindParam(':sig', $signature);
        $upd->bindParam(':id', $expense_id);
        $upd->execute();

        // All three in? Then the expense is approved.
        $check = $pdo->prepare("SELECT reviewed_at, prepared_at, acknowledged_at FROM expenses WHERE id = :id");
        $check->bindParam(':id', $expense_id);
        $check->execute();
        $now = $check->fetch(PDO::FETCH_ASSOC);

        $complete = !empty($now['reviewed_at']) && !empty($now['prepared_at']) && !empty($now['acknowledged_at']);
        $status = $complete ? 'approved' : 'partially_signed';

        $pdo->prepare("UPDATE expenses SET approval_status = :s WHERE id = :id")
            ->execute([':s' => $status, ':id' => $expense_id]);

        $_SESSION['swal_message'] = $complete
            ? 'All three signatures are in. The expense is now approved.'
            : $def['label'] . ' signed. Waiting for the next signatory.';
        $_SESSION['swal_message_type'] = 'success';
    } catch (PDOException $e) {
        $_SESSION['swal_message'] = 'Database error: ' . $e->getMessage();
        $_SESSION['swal_message_type'] = 'error';
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

