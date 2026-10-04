<?php
/**
 * actions/employee_registration-actions.php
 *
 * Every action for employee_registration.php lives in this one file: updating an
 * employee's details, their wage, their deductions, deleting one, and the view and
 * edit requests the page's buttons make.
 *
 * The page pulls this file in after its own initialisation, so it runs in the page's
 * scope. That matters for two reasons: the form-repopulation variables the page
 * initialises are assigned here and read by the template below, and a rejected form
 * therefore comes back filled in; and $message, $message_type and $swal_data are set
 * here and read by the markup.
 *
 * The block below is lifted verbatim from employee_registration.php: the queries, the
 * wage and deduction arithmetic, and the messages are unchanged.
 */

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    return;
}
if (defined('OCP_EMPLOYEE_REGISTRATION_ACTIONS_RAN')) {
    return;
}
define('OCP_EMPLOYEE_REGISTRATION_ACTIONS_RAN', true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if it's an update request for employee details
    if (isset($_POST['update_employee_details'])) {
        $id = $_POST['employee_id'];
        $employee_id = trim($_POST['employee_id_field']);
        $firstname = trim($_POST['firstname']);
        $middlename = trim($_POST['middlename']);
        $lastname = trim($_POST['lastname']);
        $suffix = trim($_POST['suffix']);
        $address = trim($_POST['address']);
        $contact_number = trim($_POST['contact_number']);
        $birth_date = trim($_POST['birth_date']);
        $marital_status = trim($_POST['marital_status']);
        $position = trim($_POST['position']);
        $hire_date = trim($_POST['hire_date']);
        $status = trim($_POST['status']);
        
        // Basic validation
        if (empty($employee_id)) {
            $swal_data = [
                'icon' => 'error',
                'title' => 'Error',
                'text' => 'Employee ID is required.'
            ];
        } elseif (empty($firstname)) {
            $swal_data = [
                'icon' => 'error',
                'title' => 'Error',
                'text' => 'First name is required.'
            ];
        } elseif (empty($lastname)) {
            $swal_data = [
                'icon' => 'error',
                'title' => 'Error',
                'text' => 'Last name is required.'
            ];
        } elseif (empty($position)) {
            $swal_data = [
                'icon' => 'error',
                'title' => 'Error',
                'text' => 'Position is required.'
            ];
        } elseif (empty($hire_date)) {
            $swal_data = [
                'icon' => 'error',
                'title' => 'Error',
                'text' => 'Hire date is required.'
            ];
        } else {
            try {
                // Check if employee ID already exists (excluding current employee)
                $checkStmt = $pdo->prepare("SELECT id FROM employee WHERE employee_id = :employee_id AND id != :id");
                $checkStmt->bindParam(':employee_id', $employee_id);
                $checkStmt->bindParam(':id', $id);
                $checkStmt->execute();
                
                if ($checkStmt->rowCount() > 0) {
                    $swal_data = [
                        'icon' => 'error',
                        'title' => 'Error',
                        'text' => 'Employee ID already exists.'
                    ];
                } else {
                    // Update employee details
                    $updateStmt = $pdo->prepare("UPDATE employee SET 
                        employee_id = :employee_id, 
                        firstname = :firstname, 
                        middlename = :middlename, 
                        lastname = :lastname, 
                        suffix = :suffix, 
                        address = :address, 
                        contact_number = :contact_number, 
                        birth_date = :birth_date, 
                        marital_status = :marital_status, 
                        position = :position, 
                        hire_date = :hire_date,
                        status = :status
                        WHERE id = :id");
                    
                    $updateStmt->bindParam(':employee_id', $employee_id);
                    $updateStmt->bindParam(':firstname', $firstname);
                    $updateStmt->bindParam(':middlename', $middlename);
                    $updateStmt->bindParam(':lastname', $lastname);
                    $updateStmt->bindParam(':suffix', $suffix);
                    $updateStmt->bindParam(':address', $address);
                    $updateStmt->bindParam(':contact_number', $contact_number);
                    $updateStmt->bindParam(':birth_date', $birth_date);
                    $updateStmt->bindParam(':marital_status', $marital_status);
                    $updateStmt->bindParam(':position', $position);
                    $updateStmt->bindParam(':hire_date', $hire_date);
                    $updateStmt->bindParam(':status', $status);
                    $updateStmt->bindParam(':id', $id);
                    
                    if ($updateStmt->execute()) {
                        $swal_data = [
                            'icon' => 'success',
                            'title' => 'Success',
                            'text' => 'Employee details updated successfully!'
                        ];
                    } else {
                        $swal_data = [
                            'icon' => 'error',
                            'title' => 'Error',
                            'text' => 'Error updating employee details. Please try again.'
                        ];
                    }
                }
            } catch(PDOException $e) {
                $swal_data = [
                    'icon' => 'error',
                    'title' => 'Database Error',
                    'text' => 'Database error: ' . $e->getMessage()
                ];
            }
        }
        
        // Clear form variables after processing edit request
        $employee_id = $firstname = $middlename = $lastname = $suffix = $address = $contact_number = $birth_date = $marital_status = $position = $hire_date = $daily_wage = $effectivity_date = '';
    }
    // Check if it's an update request for wage
    elseif (isset($_POST['update_employee_wage'])) {
        $id = $_POST['employee_id'];
        $daily_wage = trim($_POST['daily_wage']);
        $effectivity_date = trim($_POST['effectivity_date']);
        $change_reason = isset($_POST['change_reason']) ? trim($_POST['change_reason']) : '';
        
        // Validate daily wage
        if (!is_numeric($daily_wage) || $daily_wage < 0) {
            $swal_data = [
                'icon' => 'error',
                'title' => 'Error',
                'text' => 'Daily wage must be a valid positive number.'
            ];
        } elseif (empty($effectivity_date)) {
            $swal_data = [
                'icon' => 'error',
                'title' => 'Error',
                'text' => 'Effectivity date is required.'
            ];
        } else {
            try {
                // Get current wage for comparison
                $currentWageStmt = $pdo->prepare("SELECT daily_wage FROM employee WHERE id = :id");
                $currentWageStmt->bindParam(':id', $id);
                $currentWageStmt->execute();
                $current_wage = $currentWageStmt->fetchColumn();
                
                // Calculate change details
                $change_amount = $daily_wage - $current_wage;
                
                if ($current_wage != 0) {
                    $change_percentage = ($change_amount / $current_wage) * 100;
                } else {
                    $change_percentage = $daily_wage > 0 ? 100 : 0;
                }
                
                if ($change_amount > 0) {
                    $change_type = 'increase';
                } elseif ($change_amount < 0) {
                    $change_type = 'decrease';
                } else {
                    $change_type = 'no change';
                }
                
                // Update employee wage
                $updateStmt = $pdo->prepare("UPDATE employee SET daily_wage = :daily_wage WHERE id = :id");
                $updateStmt->bindParam(':daily_wage', $daily_wage);
                $updateStmt->bindParam(':id', $id);
                
                if ($updateStmt->execute()) {
                    // Check if there's already a wage change record today
                    $today = date('Y-m-d');
                    $checkHistoryStmt = $pdo->prepare("
                        SELECT id FROM wage_history 
                        WHERE employee_id = :employee_id 
                        AND DATE(changed_at) = :today 
                        AND change_type != 'no change'
                    ");
                    $checkHistoryStmt->bindParam(':employee_id', $id);
                    $checkHistoryStmt->bindParam(':today', $today);
                    $checkHistoryStmt->execute();
                    
                    if ($checkHistoryStmt->rowCount() > 0) {
                        // Update existing record for today
                        $historyStmt = $pdo->prepare("
                            UPDATE wage_history 
                            SET old_wage = :old_wage,
                                new_wage = :new_wage,
                                change_amount = :change_amount,
                                change_percentage = :change_percentage,
                                change_type = :change_type,
                                changed_by = :changed_by,
                                change_reason = :change_reason,
                                effectivity_date = :effectivity_date,
                                changed_at = CURRENT_TIMESTAMP
                            WHERE employee_id = :employee_id 
                            AND DATE(changed_at) = :today
                        ");
                        $historyStmt->bindParam(':employee_id', $id);
                        $historyStmt->bindParam(':old_wage', $current_wage);
                        $historyStmt->bindParam(':new_wage', $daily_wage);
                        $historyStmt->bindParam(':change_amount', $change_amount);
                        $historyStmt->bindParam(':change_percentage', $change_percentage);
                        $historyStmt->bindParam(':change_type', $change_type);
                        $historyStmt->bindParam(':changed_by', $_SESSION['user_id']);
                        $historyStmt->bindParam(':change_reason', $change_reason);
                        $historyStmt->bindParam(':effectivity_date', $effectivity_date);
                        $historyStmt->bindParam(':today', $today);
                        $historyStmt->execute();
                        
                        $swal_data = [
                            'icon' => 'success',
                            'title' => 'Success',
                            'text' => 'Employee daily wage updated successfully! (Previous entry today was overwritten)'
                        ];
                    } else {
                        // Insert new record
                        $historyStmt = $pdo->prepare("
                            INSERT INTO wage_history 
                            (employee_id, old_wage, new_wage, change_amount, change_percentage, change_type, changed_by, change_reason, effectivity_date) 
                            VALUES (:employee_id, :old_wage, :new_wage, :change_amount, :change_percentage, :change_type, :changed_by, :change_reason, :effectivity_date)
                        ");
                        $historyStmt->bindParam(':employee_id', $id);
                        $historyStmt->bindParam(':old_wage', $current_wage);
                        $historyStmt->bindParam(':new_wage', $daily_wage);
                        $historyStmt->bindParam(':change_amount', $change_amount);
                        $historyStmt->bindParam(':change_percentage', $change_percentage);
                        $historyStmt->bindParam(':change_type', $change_type);
                        $historyStmt->bindParam(':changed_by', $_SESSION['user_id']);
                        $historyStmt->bindParam(':change_reason', $change_reason);
                        $historyStmt->bindParam(':effectivity_date', $effectivity_date);
                        $historyStmt->execute();
                        
                        $swal_data = [
                            'icon' => 'success',
                            'title' => 'Success',
                            'text' => 'Employee daily wage updated successfully!'
                        ];
                    }
                } else {
                    $swal_data = [
                        'icon' => 'error',
                        'title' => 'Error',
                        'text' => 'Error updating employee daily wage. Please try again.'
                    ];
                }
            } catch(PDOException $e) {
                $swal_data = [
                    'icon' => 'error',
                    'title' => 'Database Error',
                    'text' => 'Database error: ' . $e->getMessage()
                ];
            }
        }
        
        // Clear form variables after processing wage update request
        $employee_id = $firstname = $middlename = $lastname = $suffix = $address = $contact_number = $birth_date = $marital_status = $position = $hire_date = $daily_wage = $effectivity_date = '';
    }
    // Check if it's a deduction update request
    elseif (isset($_POST['update_employee_deductions'])) {
        $id = $_POST['employee_id'];
        $change_reason = isset($_POST['change_reason']) ? trim($_POST['change_reason']) : '';
        
        try {
            $pdo->beginTransaction();
            
            $deduction_changes = [];
            $today = date('Y-m-d');
            
            // Process Cash Advance
            $cash_advance_amount = !empty($_POST['cash_advance_amount']) ? floatval($_POST['cash_advance_amount']) : 0;
            $cash_advance_from = !empty($_POST['cash_advance_from']) ? $_POST['cash_advance_from'] : null;
            $cash_advance_to = !empty($_POST['cash_advance_to']) ? $_POST['cash_advance_to'] : null;
            $cash_advance_notes = !empty($_POST['cash_advance_notes']) ? trim($_POST['cash_advance_notes']) : '';
            
            // Get current cash advance
            $currentCAStmt = $pdo->prepare("SELECT * FROM employee_deductions WHERE employee_id = :employee_id AND deduction_type = 'cash_advance'");
            $currentCAStmt->bindParam(':employee_id', $id);
            $currentCAStmt->execute();
            $current_ca = $currentCAStmt->fetch(PDO::FETCH_ASSOC);
            
            $old_ca_amount = $current_ca ? $current_ca['amount'] : 0;
            $old_ca_from = $current_ca ? $current_ca['from_date'] : null;
            $old_ca_to = $current_ca ? $current_ca['to_date'] : null;
            $old_ca_notes = $current_ca ? $current_ca['notes'] : '';
            
            // Calculate change details
            $ca_change_amount = $cash_advance_amount - $old_ca_amount;
            
            if ($old_ca_amount != 0) {
                $ca_change_percentage = ($ca_change_amount / $old_ca_amount) * 100;
            } else {
                $ca_change_percentage = $cash_advance_amount > 0 ? 100 : 0;
            }
            
            if ($ca_change_amount > 0) {
                $ca_change_type = 'increase';
            } elseif ($ca_change_amount < 0) {
                $ca_change_type = 'decrease';
            } else {
                $ca_change_type = 'no change';
            }
            
            // Update or insert cash advance
            if ($cash_advance_amount > 0) {
                if ($current_ca) {
                    // Update existing
                    $updateCAStmt = $pdo->prepare("UPDATE employee_deductions SET 
                        amount = :amount,
                        from_date = :from_date,
                        to_date = :to_date,
                        notes = :notes
                        WHERE employee_id = :employee_id AND deduction_type = 'cash_advance'");
                    
                    $updateCAStmt->bindParam(':amount', $cash_advance_amount);
                    $updateCAStmt->bindParam(':from_date', $cash_advance_from);
                    $updateCAStmt->bindParam(':to_date', $cash_advance_to);
                    $updateCAStmt->bindParam(':notes', $cash_advance_notes);
                    $updateCAStmt->bindParam(':employee_id', $id);
                    $updateCAStmt->execute();
                } else {
                    // Insert new
                    $insertCAStmt = $pdo->prepare("INSERT INTO employee_deductions 
                        (employee_id, deduction_type, amount, from_date, to_date, notes, created_by) 
                        VALUES (:employee_id, 'cash_advance', :amount, :from_date, :to_date, :notes, :created_by)");
                    
                    $insertCAStmt->bindParam(':employee_id', $id);
                    $insertCAStmt->bindParam(':amount', $cash_advance_amount);
                    $insertCAStmt->bindParam(':from_date', $cash_advance_from);
                    $insertCAStmt->bindParam(':to_date', $cash_advance_to);
                    $insertCAStmt->bindParam(':notes', $cash_advance_notes);
                    $insertCAStmt->bindParam(':created_by', $_SESSION['user_id']);
                    $insertCAStmt->execute();
                }
            } else {
                // Delete if amount is 0
                if ($current_ca) {
                    $deleteCAStmt = $pdo->prepare("DELETE FROM employee_deductions WHERE employee_id = :employee_id AND deduction_type = 'cash_advance'");
                    $deleteCAStmt->bindParam(':employee_id', $id);
                    $deleteCAStmt->execute();
                }
            }
            
            // Record cash advance history if there's a change
            if ($ca_change_type != 'no change' || $old_ca_from != $cash_advance_from || $old_ca_to != $cash_advance_to || $old_ca_notes != $cash_advance_notes) {
                // Check if there's already a deduction history record today
                $checkCAHistoryStmt = $pdo->prepare("
                    SELECT id FROM employee_deductions_history 
                    WHERE employee_id = :employee_id 
                    AND deduction_type = 'cash_advance'
                    AND DATE(changed_at) = :today 
                ");
                $checkCAHistoryStmt->bindParam(':employee_id', $id);
                $checkCAHistoryStmt->bindParam(':today', $today);
                $checkCAHistoryStmt->execute();
                
                if ($checkCAHistoryStmt->rowCount() > 0) {
                    // Update existing record for today
                    $caHistoryStmt = $pdo->prepare("
                        UPDATE employee_deductions_history 
                        SET old_amount = :old_amount,
                            new_amount = :new_amount,
                            change_amount = :change_amount,
                            change_percentage = :change_percentage,
                            change_type = :change_type,
                            old_from_date = :old_from_date,
                            new_from_date = :new_from_date,
                            old_to_date = :old_to_date,
                            new_to_date = :new_to_date,
                            old_notes = :old_notes,
                            new_notes = :new_notes,
                            changed_by = :changed_by,
                            change_reason = :change_reason,
                            changed_at = CURRENT_TIMESTAMP
                        WHERE employee_id = :employee_id 
                        AND deduction_type = 'cash_advance'
                        AND DATE(changed_at) = :today
                    ");
                } else {
                    // Insert new record
                    $caHistoryStmt = $pdo->prepare("
                        INSERT INTO employee_deductions_history 
                        (employee_id, deduction_type, old_amount, new_amount, change_amount, change_percentage, change_type, 
                         old_from_date, new_from_date, old_to_date, new_to_date, old_notes, new_notes, changed_by, change_reason) 
                        VALUES (:employee_id, 'cash_advance', :old_amount, :new_amount, :change_amount, :change_percentage, :change_type,
                                :old_from_date, :new_from_date, :old_to_date, :new_to_date, :old_notes, :new_notes, :changed_by, :change_reason)
                    ");
                }
                
                $caHistoryStmt->bindParam(':employee_id', $id);
                $caHistoryStmt->bindParam(':old_amount', $old_ca_amount);
                $caHistoryStmt->bindParam(':new_amount', $cash_advance_amount);
                $caHistoryStmt->bindParam(':change_amount', $ca_change_amount);
                $caHistoryStmt->bindParam(':change_percentage', $ca_change_percentage);
                $caHistoryStmt->bindParam(':change_type', $ca_change_type);
                $caHistoryStmt->bindParam(':old_from_date', $old_ca_from);
                $caHistoryStmt->bindParam(':new_from_date', $cash_advance_from);
                $caHistoryStmt->bindParam(':old_to_date', $old_ca_to);
                $caHistoryStmt->bindParam(':new_to_date', $cash_advance_to);
                $caHistoryStmt->bindParam(':old_notes', $old_ca_notes);
                $caHistoryStmt->bindParam(':new_notes', $cash_advance_notes);
                $caHistoryStmt->bindParam(':changed_by', $_SESSION['user_id']);
                $caHistoryStmt->bindParam(':change_reason', $change_reason);
                
                if ($checkCAHistoryStmt->rowCount() > 0) {
                    $caHistoryStmt->bindParam(':today', $today);
                }
                
                $caHistoryStmt->execute();
                
                $deduction_changes['cash_advance'] = [
                    'old' => $old_ca_amount,
                    'new' => $cash_advance_amount,
                    'change' => $ca_change_amount,
                    'percentage' => $ca_change_percentage,
                    'type' => $ca_change_type
                ];
            }
            
            // Process SSS
            $sss_amount = !empty($_POST['sss_amount']) ? floatval($_POST['sss_amount']) : 0;
            $sss_effectivity = !empty($_POST['sss_effectivity']) ? $_POST['sss_effectivity'] : null;
            $sss_notes = !empty($_POST['sss_notes']) ? trim($_POST['sss_notes']) : '';
            
            $currentSSSStmt = $pdo->prepare("SELECT * FROM employee_deductions WHERE employee_id = :employee_id AND deduction_type = 'sss'");
            $currentSSSStmt->bindParam(':employee_id', $id);
            $currentSSSStmt->execute();
            $current_sss = $currentSSSStmt->fetch(PDO::FETCH_ASSOC);
            
            $old_sss_amount = $current_sss ? $current_sss['amount'] : 0;
            $old_sss_effectivity = $current_sss ? $current_sss['effectivity_date'] : null;
            $old_sss_notes = $current_sss ? $current_sss['notes'] : '';
            
            $sss_change_amount = $sss_amount - $old_sss_amount;
            
            if ($old_sss_amount != 0) {
                $sss_change_percentage = ($sss_change_amount / $old_sss_amount) * 100;
            } else {
                $sss_change_percentage = $sss_amount > 0 ? 100 : 0;
            }
            
            if ($sss_change_amount > 0) {
                $sss_change_type = 'increase';
            } elseif ($sss_change_amount < 0) {
                $sss_change_type = 'decrease';
            } else {
                $sss_change_type = 'no change';
            }
            
            if ($sss_amount > 0) {
                if ($current_sss) {
                    $updateSSSStmt = $pdo->prepare("UPDATE employee_deductions SET 
                        amount = :amount,
                        effectivity_date = :effectivity_date,
                        notes = :notes
                        WHERE employee_id = :employee_id AND deduction_type = 'sss'");
                    
                    $updateSSSStmt->bindParam(':amount', $sss_amount);
                    $updateSSSStmt->bindParam(':effectivity_date', $sss_effectivity);
                    $updateSSSStmt->bindParam(':notes', $sss_notes);
                    $updateSSSStmt->bindParam(':employee_id', $id);
                    $updateSSSStmt->execute();
                } else {
                    $insertSSSStmt = $pdo->prepare("INSERT INTO employee_deductions 
                        (employee_id, deduction_type, amount, effectivity_date, notes, created_by) 
                        VALUES (:employee_id, 'sss', :amount, :effectivity_date, :notes, :created_by)");
                    
                    $insertSSSStmt->bindParam(':employee_id', $id);
                    $insertSSSStmt->bindParam(':amount', $sss_amount);
                    $insertSSSStmt->bindParam(':effectivity_date', $sss_effectivity);
                    $insertSSSStmt->bindParam(':notes', $sss_notes);
                    $insertSSSStmt->bindParam(':created_by', $_SESSION['user_id']);
                    $insertSSSStmt->execute();
                }
            } else {
                if ($current_sss) {
                    $deleteSSSStmt = $pdo->prepare("DELETE FROM employee_deductions WHERE employee_id = :employee_id AND deduction_type = 'sss'");
                    $deleteSSSStmt->bindParam(':employee_id', $id);
                    $deleteSSSStmt->execute();
                }
            }
            
            if ($sss_change_type != 'no change' || $old_sss_effectivity != $sss_effectivity || $old_sss_notes != $sss_notes) {
                $checkSSSHistoryStmt = $pdo->prepare("
                    SELECT id FROM employee_deductions_history 
                    WHERE employee_id = :employee_id 
                    AND deduction_type = 'sss'
                    AND DATE(changed_at) = :today 
                ");
                $checkSSSHistoryStmt->bindParam(':employee_id', $id);
                $checkSSSHistoryStmt->bindParam(':today', $today);
                $checkSSSHistoryStmt->execute();
                
                if ($checkSSSHistoryStmt->rowCount() > 0) {
                    $sssHistoryStmt = $pdo->prepare("
                        UPDATE employee_deductions_history 
                        SET old_amount = :old_amount,
                            new_amount = :new_amount,
                            change_amount = :change_amount,
                            change_percentage = :change_percentage,
                            change_type = :change_type,
                            old_effectivity_date = :old_effectivity_date,
                            new_effectivity_date = :new_effectivity_date,
                            old_notes = :old_notes,
                            new_notes = :new_notes,
                            changed_by = :changed_by,
                            change_reason = :change_reason,
                            changed_at = CURRENT_TIMESTAMP
                        WHERE employee_id = :employee_id 
                        AND deduction_type = 'sss'
                        AND DATE(changed_at) = :today
                    ");
                } else {
                    $sssHistoryStmt = $pdo->prepare("
                        INSERT INTO employee_deductions_history 
                        (employee_id, deduction_type, old_amount, new_amount, change_amount, change_percentage, change_type,
                         old_effectivity_date, new_effectivity_date, old_notes, new_notes, changed_by, change_reason) 
                        VALUES (:employee_id, 'sss', :old_amount, :new_amount, :change_amount, :change_percentage, :change_type,
                                :old_effectivity_date, :new_effectivity_date, :old_notes, :new_notes, :changed_by, :change_reason)
                    ");
                }
                
                $sssHistoryStmt->bindParam(':employee_id', $id);
                $sssHistoryStmt->bindParam(':old_amount', $old_sss_amount);
                $sssHistoryStmt->bindParam(':new_amount', $sss_amount);
                $sssHistoryStmt->bindParam(':change_amount', $sss_change_amount);
                $sssHistoryStmt->bindParam(':change_percentage', $sss_change_percentage);
                $sssHistoryStmt->bindParam(':change_type', $sss_change_type);
                $sssHistoryStmt->bindParam(':old_effectivity_date', $old_sss_effectivity);
                $sssHistoryStmt->bindParam(':new_effectivity_date', $sss_effectivity);
                $sssHistoryStmt->bindParam(':old_notes', $old_sss_notes);
                $sssHistoryStmt->bindParam(':new_notes', $sss_notes);
                $sssHistoryStmt->bindParam(':changed_by', $_SESSION['user_id']);
                $sssHistoryStmt->bindParam(':change_reason', $change_reason);
                
                if ($checkSSSHistoryStmt->rowCount() > 0) {
                    $sssHistoryStmt->bindParam(':today', $today);
                }
                
                $sssHistoryStmt->execute();
                
                $deduction_changes['sss'] = [
                    'old' => $old_sss_amount,
                    'new' => $sss_amount,
                    'change' => $sss_change_amount,
                    'percentage' => $sss_change_percentage,
                    'type' => $sss_change_type
                ];
            }
            
            // Process Pag-IBIG
            $pagibig_amount = !empty($_POST['pagibig_amount']) ? floatval($_POST['pagibig_amount']) : 0;
            $pagibig_effectivity = !empty($_POST['pagibig_effectivity']) ? $_POST['pagibig_effectivity'] : null;
            $pagibig_notes = !empty($_POST['pagibig_notes']) ? trim($_POST['pagibig_notes']) : '';
            
            $currentPagibigStmt = $pdo->prepare("SELECT * FROM employee_deductions WHERE employee_id = :employee_id AND deduction_type = 'pag_ibig'");
            $currentPagibigStmt->bindParam(':employee_id', $id);
            $currentPagibigStmt->execute();
            $current_pagibig = $currentPagibigStmt->fetch(PDO::FETCH_ASSOC);
            
            $old_pagibig_amount = $current_pagibig ? $current_pagibig['amount'] : 0;
            $old_pagibig_effectivity = $current_pagibig ? $current_pagibig['effectivity_date'] : null;
            $old_pagibig_notes = $current_pagibig ? $current_pagibig['notes'] : '';
            
            $pagibig_change_amount = $pagibig_amount - $old_pagibig_amount;
            
            if ($old_pagibig_amount != 0) {
                $pagibig_change_percentage = ($pagibig_change_amount / $old_pagibig_amount) * 100;
            } else {
                $pagibig_change_percentage = $pagibig_amount > 0 ? 100 : 0;
            }
            
            if ($pagibig_change_amount > 0) {
                $pagibig_change_type = 'increase';
            } elseif ($pagibig_change_amount < 0) {
                $pagibig_change_type = 'decrease';
            } else {
                $pagibig_change_type = 'no change';
            }
            
            if ($pagibig_amount > 0) {
                if ($current_pagibig) {
                    $updatePagibigStmt = $pdo->prepare("UPDATE employee_deductions SET 
                        amount = :amount,
                        effectivity_date = :effectivity_date,
                        notes = :notes
                        WHERE employee_id = :employee_id AND deduction_type = 'pag_ibig'");
                    
                    $updatePagibigStmt->bindParam(':amount', $pagibig_amount);
                    $updatePagibigStmt->bindParam(':effectivity_date', $pagibig_effectivity);
                    $updatePagibigStmt->bindParam(':notes', $pagibig_notes);
                    $updatePagibigStmt->bindParam(':employee_id', $id);
                    $updatePagibigStmt->execute();
                } else {
                    $insertPagibigStmt = $pdo->prepare("INSERT INTO employee_deductions 
                        (employee_id, deduction_type, amount, effectivity_date, notes, created_by) 
                        VALUES (:employee_id, 'pag_ibig', :amount, :effectivity_date, :notes, :created_by)");
                    
                    $insertPagibigStmt->bindParam(':employee_id', $id);
                    $insertPagibigStmt->bindParam(':amount', $pagibig_amount);
                    $insertPagibigStmt->bindParam(':effectivity_date', $pagibig_effectivity);
                    $insertPagibigStmt->bindParam(':notes', $pagibig_notes);
                    $insertPagibigStmt->bindParam(':created_by', $_SESSION['user_id']);
                    $insertPagibigStmt->execute();
                }
            } else {
                if ($current_pagibig) {
                    $deletePagibigStmt = $pdo->prepare("DELETE FROM employee_deductions WHERE employee_id = :employee_id AND deduction_type = 'pag_ibig'");
                    $deletePagibigStmt->bindParam(':employee_id', $id);
                    $deletePagibigStmt->execute();
                }
            }
            
            if ($pagibig_change_type != 'no change' || $old_pagibig_effectivity != $pagibig_effectivity || $old_pagibig_notes != $pagibig_notes) {
                $checkPagibigHistoryStmt = $pdo->prepare("
                    SELECT id FROM employee_deductions_history 
                    WHERE employee_id = :employee_id 
                    AND deduction_type = 'pag_ibig'
                    AND DATE(changed_at) = :today 
                ");
                $checkPagibigHistoryStmt->bindParam(':employee_id', $id);
                $checkPagibigHistoryStmt->bindParam(':today', $today);
                $checkPagibigHistoryStmt->execute();
                
                if ($checkPagibigHistoryStmt->rowCount() > 0) {
                    $pagibigHistoryStmt = $pdo->prepare("
                        UPDATE employee_deductions_history 
                        SET old_amount = :old_amount,
                            new_amount = :new_amount,
                            change_amount = :change_amount,
                            change_percentage = :change_percentage,
                            change_type = :change_type,
                            old_effectivity_date = :old_effectivity_date,
                            new_effectivity_date = :new_effectivity_date,
                            old_notes = :old_notes,
                            new_notes = :new_notes,
                            changed_by = :changed_by,
                            change_reason = :change_reason,
                            changed_at = CURRENT_TIMESTAMP
                        WHERE employee_id = :employee_id 
                        AND deduction_type = 'pag_ibig'
                        AND DATE(changed_at) = :today
                    ");
                } else {
                    $pagibigHistoryStmt = $pdo->prepare("
                        INSERT INTO employee_deductions_history 
                        (employee_id, deduction_type, old_amount, new_amount, change_amount, change_percentage, change_type,
                         old_effectivity_date, new_effectivity_date, old_notes, new_notes, changed_by, change_reason) 
                        VALUES (:employee_id, 'pag_ibig', :old_amount, :new_amount, :change_amount, :change_percentage, :change_type,
                                :old_effectivity_date, :new_effectivity_date, :old_notes, :new_notes, :changed_by, :change_reason)
                    ");
                }
                
                $pagibigHistoryStmt->bindParam(':employee_id', $id);
                $pagibigHistoryStmt->bindParam(':old_amount', $old_pagibig_amount);
                $pagibigHistoryStmt->bindParam(':new_amount', $pagibig_amount);
                $pagibigHistoryStmt->bindParam(':change_amount', $pagibig_change_amount);
                $pagibigHistoryStmt->bindParam(':change_percentage', $pagibig_change_percentage);
                $pagibigHistoryStmt->bindParam(':change_type', $pagibig_change_type);
                $pagibigHistoryStmt->bindParam(':old_effectivity_date', $old_pagibig_effectivity);
                $pagibigHistoryStmt->bindParam(':new_effectivity_date', $pagibig_effectivity);
                $pagibigHistoryStmt->bindParam(':old_notes', $old_pagibig_notes);
                $pagibigHistoryStmt->bindParam(':new_notes', $pagibig_notes);
                $pagibigHistoryStmt->bindParam(':changed_by', $_SESSION['user_id']);
                $pagibigHistoryStmt->bindParam(':change_reason', $change_reason);
                
                if ($checkPagibigHistoryStmt->rowCount() > 0) {
                    $pagibigHistoryStmt->bindParam(':today', $today);
                }
                
                $pagibigHistoryStmt->execute();
                
                $deduction_changes['pag_ibig'] = [
                    'old' => $old_pagibig_amount,
                    'new' => $pagibig_amount,
                    'change' => $pagibig_change_amount,
                    'percentage' => $pagibig_change_percentage,
                    'type' => $pagibig_change_type
                ];
            }
            
            // Process PhilHealth
            $philhealth_amount = !empty($_POST['philhealth_amount']) ? floatval($_POST['philhealth_amount']) : 0;
            $philhealth_effectivity = !empty($_POST['philhealth_effectivity']) ? $_POST['philhealth_effectivity'] : null;
            $philhealth_notes = !empty($_POST['philhealth_notes']) ? trim($_POST['philhealth_notes']) : '';
            
            $currentPhilhealthStmt = $pdo->prepare("SELECT * FROM employee_deductions WHERE employee_id = :employee_id AND deduction_type = 'philhealth'");
            $currentPhilhealthStmt->bindParam(':employee_id', $id);
            $currentPhilhealthStmt->execute();
            $current_philhealth = $currentPhilhealthStmt->fetch(PDO::FETCH_ASSOC);
            
            $old_philhealth_amount = $current_philhealth ? $current_philhealth['amount'] : 0;
            $old_philhealth_effectivity = $current_philhealth ? $current_philhealth['effectivity_date'] : null;
            $old_philhealth_notes = $current_philhealth ? $current_philhealth['notes'] : '';
            
            $philhealth_change_amount = $philhealth_amount - $old_philhealth_amount;
            
            if ($old_philhealth_amount != 0) {
                $philhealth_change_percentage = ($philhealth_change_amount / $old_philhealth_amount) * 100;
            } else {
                $philhealth_change_percentage = $philhealth_amount > 0 ? 100 : 0;
            }
            
            if ($philhealth_change_amount > 0) {
                $philhealth_change_type = 'increase';
            } elseif ($philhealth_change_amount < 0) {
                $philhealth_change_type = 'decrease';
            } else {
                $philhealth_change_type = 'no change';
            }
            
            if ($philhealth_amount > 0) {
                if ($current_philhealth) {
                    $updatePhilhealthStmt = $pdo->prepare("UPDATE employee_deductions SET 
                        amount = :amount,
                        effectivity_date = :effectivity_date,
                        notes = :notes
                        WHERE employee_id = :employee_id AND deduction_type = 'philhealth'");
                    
                    $updatePhilhealthStmt->bindParam(':amount', $philhealth_amount);
                    $updatePhilhealthStmt->bindParam(':effectivity_date', $philhealth_effectivity);
                    $updatePhilhealthStmt->bindParam(':notes', $philhealth_notes);
                    $updatePhilhealthStmt->bindParam(':employee_id', $id);
                    $updatePhilhealthStmt->execute();
                } else {
                    $insertPhilhealthStmt = $pdo->prepare("INSERT INTO employee_deductions 
                        (employee_id, deduction_type, amount, effectivity_date, notes, created_by) 
                        VALUES (:employee_id, 'philhealth', :amount, :effectivity_date, :notes, :created_by)");
                    
                    $insertPhilhealthStmt->bindParam(':employee_id', $id);
                    $insertPhilhealthStmt->bindParam(':amount', $philhealth_amount);
                    $insertPhilhealthStmt->bindParam(':effectivity_date', $philhealth_effectivity);
                    $insertPhilhealthStmt->bindParam(':notes', $philhealth_notes);
                    $insertPhilhealthStmt->bindParam(':created_by', $_SESSION['user_id']);
                    $insertPhilhealthStmt->execute();
                }
            } else {
                if ($current_philhealth) {
                    $deletePhilhealthStmt = $pdo->prepare("DELETE FROM employee_deductions WHERE employee_id = :employee_id AND deduction_type = 'philhealth'");
                    $deletePhilhealthStmt->bindParam(':employee_id', $id);
                    $deletePhilhealthStmt->execute();
                }
            }
            
            if ($philhealth_change_type != 'no change' || $old_philhealth_effectivity != $philhealth_effectivity || $old_philhealth_notes != $philhealth_notes) {
                $checkPhilhealthHistoryStmt = $pdo->prepare("
                    SELECT id FROM employee_deductions_history 
                    WHERE employee_id = :employee_id 
                    AND deduction_type = 'philhealth'
                    AND DATE(changed_at) = :today 
                ");
                $checkPhilhealthHistoryStmt->bindParam(':employee_id', $id);
                $checkPhilhealthHistoryStmt->bindParam(':today', $today);
                $checkPhilhealthHistoryStmt->execute();
                
                if ($checkPhilhealthHistoryStmt->rowCount() > 0) {
                    $philhealthHistoryStmt = $pdo->prepare("
                        UPDATE employee_deductions_history 
                        SET old_amount = :old_amount,
                            new_amount = :new_amount,
                            change_amount = :change_amount,
                            change_percentage = :change_percentage,
                            change_type = :change_type,
                            old_effectivity_date = :old_effectivity_date,
                            new_effectivity_date = :new_effectivity_date,
                            old_notes = :old_notes,
                            new_notes = :new_notes,
                            changed_by = :changed_by,
                            change_reason = :change_reason,
                            changed_at = CURRENT_TIMESTAMP
                        WHERE employee_id = :employee_id 
                        AND deduction_type = 'philhealth'
                        AND DATE(changed_at) = :today
                    ");
                } else {
                    $philhealthHistoryStmt = $pdo->prepare("
                        INSERT INTO employee_deductions_history 
                        (employee_id, deduction_type, old_amount, new_amount, change_amount, change_percentage, change_type,
                         old_effectivity_date, new_effectivity_date, old_notes, new_notes, changed_by, change_reason) 
                        VALUES (:employee_id, 'philhealth', :old_amount, :new_amount, :change_amount, :change_percentage, :change_type,
                                :old_effectivity_date, :new_effectivity_date, :old_notes, :new_notes, :changed_by, :change_reason)
                    ");
                }
                
                $philhealthHistoryStmt->bindParam(':employee_id', $id);
                $philhealthHistoryStmt->bindParam(':old_amount', $old_philhealth_amount);
                $philhealthHistoryStmt->bindParam(':new_amount', $philhealth_amount);
                $philhealthHistoryStmt->bindParam(':change_amount', $philhealth_change_amount);
                $philhealthHistoryStmt->bindParam(':change_percentage', $philhealth_change_percentage);
                $philhealthHistoryStmt->bindParam(':change_type', $philhealth_change_type);
                $philhealthHistoryStmt->bindParam(':old_effectivity_date', $old_philhealth_effectivity);
                $philhealthHistoryStmt->bindParam(':new_effectivity_date', $philhealth_effectivity);
                $philhealthHistoryStmt->bindParam(':old_notes', $old_philhealth_notes);
                $philhealthHistoryStmt->bindParam(':new_notes', $philhealth_notes);
                $philhealthHistoryStmt->bindParam(':changed_by', $_SESSION['user_id']);
                $philhealthHistoryStmt->bindParam(':change_reason', $change_reason);
                
                if ($checkPhilhealthHistoryStmt->rowCount() > 0) {
                    $philhealthHistoryStmt->bindParam(':today', $today);
                }
                
                $philhealthHistoryStmt->execute();
                
                $deduction_changes['philhealth'] = [
                    'old' => $old_philhealth_amount,
                    'new' => $philhealth_amount,
                    'change' => $philhealth_change_amount,
                    'percentage' => $philhealth_change_percentage,
                    'type' => $philhealth_change_type
                ];
            }
            
            // Log all deduction changes to wage_history if there are any changes
            if (!empty($deduction_changes)) {
                $deduction_changes_json = json_encode($deduction_changes);
                
                // Check if there's already a wage history record today
                $checkWageHistoryStmt = $pdo->prepare("
                    SELECT id FROM wage_history 
                    WHERE employee_id = :employee_id 
                    AND DATE(changed_at) = :today 
                    ORDER BY changed_at DESC LIMIT 1
                ");
                $checkWageHistoryStmt->bindParam(':employee_id', $id);
                $checkWageHistoryStmt->bindParam(':today', $today);
                $checkWageHistoryStmt->execute();
                
                if ($checkWageHistoryStmt->rowCount() > 0) {
                    $wage_history_id = $checkWageHistoryStmt->fetchColumn();
                    $updateWageHistoryStmt = $pdo->prepare("UPDATE wage_history SET deduction_changes = :deduction_changes WHERE id = :id");
                    $updateWageHistoryStmt->bindParam(':deduction_changes', $deduction_changes_json);
                    $updateWageHistoryStmt->bindParam(':id', $wage_history_id);
                    $updateWageHistoryStmt->execute();
                } else {
                    // Get current wage
                    $wageStmt = $pdo->prepare("SELECT daily_wage FROM employee WHERE id = :id");
                    $wageStmt->bindParam(':id', $id);
                    $wageStmt->execute();
                    $current_wage = $wageStmt->fetchColumn();
                    
                    // Insert a wage history record with deduction changes only
                    $insertWageHistoryStmt = $pdo->prepare("
                        INSERT INTO wage_history 
                        (employee_id, old_wage, new_wage, change_amount, change_percentage, change_type, changed_by, change_reason, deduction_changes) 
                        VALUES (:employee_id, :old_wage, :new_wage, 0, 0, 'no change', :changed_by, :change_reason, :deduction_changes)
                    ");
                    $insertWageHistoryStmt->bindParam(':employee_id', $id);
                    $insertWageHistoryStmt->bindParam(':old_wage', $current_wage);
                    $insertWageHistoryStmt->bindParam(':new_wage', $current_wage);
                    $insertWageHistoryStmt->bindParam(':changed_by', $_SESSION['user_id']);
                    $insertWageHistoryStmt->bindParam(':change_reason', $change_reason);
                    $insertWageHistoryStmt->bindParam(':deduction_changes', $deduction_changes_json);
                    $insertWageHistoryStmt->execute();
                }
            }
            
            $pdo->commit();
            
            $swal_data = [
                'icon' => 'success',
                'title' => 'Success',
                'text' => 'Employee deductions updated successfully!'
            ];
            
        } catch(PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $swal_data = [
                'icon' => 'error',
                'title' => 'Database Error',
                'text' => 'Database error: ' . $e->getMessage()
            ];
        }
    }
    // Check if it's a delete request
    elseif (isset($_POST['delete_employee'])) {
        $id = $_POST['employee_id'];
        
        try {
            // Delete employee (deductions and history will be deleted automatically due to CASCADE)
            $deleteStmt = $pdo->prepare("DELETE FROM employee WHERE id = :id");
            $deleteStmt->bindParam(':id', $id);
            
            if ($deleteStmt->execute()) {
                $swal_data = [
                    'icon' => 'success',
                    'title' => 'Success',
                    'text' => 'Employee deleted successfully!'
                ];
            } else {
                $swal_data = [
                    'icon' => 'error',
                    'title' => 'Error',
                    'text' => 'Error deleting employee. Please try again.'
                ];
            }
        } catch(PDOException $e) {
            $swal_data = [
                'icon' => 'error',
                'title' => 'Database Error',
                'text' => 'Database error: ' . $e->getMessage()
            ];
        }
        
        // Clear form variables after processing delete request
        $employee_id = $firstname = $middlename = $lastname = $suffix = $address = $contact_number = $birth_date = $marital_status = $position = $hire_date = $daily_wage = $effectivity_date = '';
    } 
    // Check if it's a view request
    elseif (isset($_POST['view_employee'])) {
        $id = $_POST['employee_id'];
        
        try {
            $stmt = $pdo->prepare("SELECT * FROM employee WHERE id = :id");
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            $view_employee = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get current employee deductions
            $deductionsStmt = $pdo->prepare("SELECT * FROM employee_deductions WHERE employee_id = :employee_id ORDER BY deduction_type");
            $deductionsStmt->bindParam(':employee_id', $id);
            $deductionsStmt->execute();
            $view_employee_deductions = $deductionsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get deduction history
            $deductionsHistoryStmt = $pdo->prepare("
                SELECT edh.*, u.firstname as changed_by_firstname, u.lastname as changed_by_lastname
                FROM employee_deductions_history edh
                JOIN users u ON edh.changed_by = u.id
                WHERE edh.employee_id = :employee_id
                ORDER BY edh.changed_at DESC
            ");
            $deductionsHistoryStmt->bindParam(':employee_id', $id);
            $deductionsHistoryStmt->execute();
            $view_deductions_history = $deductionsHistoryStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Organize current deductions by type
            $view_deductions_by_type = [];
            foreach ($view_employee_deductions as $deduction) {
                $view_deductions_by_type[$deduction['deduction_type']] = $deduction;
            }
            
        } catch(PDOException $e) {
            $swal_data = [
                'icon' => 'error',
                'title' => 'Error',
                'text' => 'Error fetching employee details: ' . $e->getMessage()
            ];
        }
        
        // Clear form variables after processing view request
        $employee_id = $firstname = $middlename = $lastname = $suffix = $address = $contact_number = $birth_date = $marital_status = $position = $hire_date = $daily_wage = $effectivity_date = '';
    }
    // Check if it's an edit wage request
    elseif (isset($_POST['edit_wage'])) {
        $id = $_POST['employee_id'];
        
        try {
            $stmt = $pdo->prepare("SELECT * FROM employee WHERE id = :id");
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            $edit_employee_wage = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            $swal_data = [
                'icon' => 'error',
                'title' => 'Error',
                'text' => 'Error fetching employee details: ' . $e->getMessage()
            ];
        }
        
        // Clear form variables after processing edit wage request
        $employee_id = $firstname = $middlename = $lastname = $suffix = $address = $contact_number = $birth_date = $marital_status = $position = $hire_date = $daily_wage = $effectivity_date = '';
    }
    // Check if it's an edit details request
    elseif (isset($_POST['edit_details'])) {
        $id = $_POST['employee_id'];
        
        try {
            $stmt = $pdo->prepare("SELECT * FROM employee WHERE id = :id");
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            $edit_employee_details = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            $swal_data = [
                'icon' => 'error',
                'title' => 'Error',
                'text' => 'Error fetching employee details: ' . $e->getMessage()
            ];
        }
        
        // Clear form variables after processing edit details request
        $employee_id = $firstname = $middlename = $lastname = $suffix = $address = $contact_number = $birth_date = $marital_status = $position = $hire_date = $daily_wage = $effectivity_date = '';
    }
    // Check if it's an edit deductions request
    elseif (isset($_POST['edit_deductions'])) {
        $id = $_POST['employee_id'];
        
        try {
            $stmt = $pdo->prepare("SELECT * FROM employee WHERE id = :id");
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            $edit_employee_deductions = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get current deductions
            $deductionsStmt = $pdo->prepare("SELECT * FROM employee_deductions WHERE employee_id = :employee_id");
            $deductionsStmt->bindParam(':employee_id', $id);
            $deductionsStmt->execute();
            $current_deductions = $deductionsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Organize current deductions by type
            $edit_deductions_data = [];
            foreach ($current_deductions as $deduction) {
                $edit_deductions_data[$deduction['deduction_type']] = $deduction;
            }
            
        } catch(PDOException $e) {
            $swal_data = [
                'icon' => 'error',
                'title' => 'Error',
                'text' => 'Error fetching employee details: ' . $e->getMessage()
            ];
        }
        
        // Clear form variables after processing edit deductions request
        $employee_id = $firstname = $middlename = $lastname = $suffix = $address = $contact_number = $birth_date = $marital_status = $position = $hire_date = $daily_wage = $effectivity_date = '';
    }
    // Check if it's a view wage history request
    elseif (isset($_POST['view_wage_history'])) {
        $id = $_POST['employee_id'];
        
        try {
            $stmt = $pdo->prepare("SELECT * FROM employee WHERE id = :id");
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            $view_wage_history_employee = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            $swal_data = [
                'icon' => 'error',
                'title' => 'Error',
                'text' => 'Error fetching employee details: ' . $e->getMessage()
            ];
        }
        
        // Clear form variables after processing wage history request
        $employee_id = $firstname = $middlename = $lastname = $suffix = $address = $contact_number = $birth_date = $marital_status = $position = $hire_date = $daily_wage = $effectivity_date = '';
    }
    // Check if it's a view deduction history request
    elseif (isset($_POST['view_deduction_history'])) {
        $id = $_POST['employee_id'];
        
        try {
            $stmt = $pdo->prepare("SELECT * FROM employee WHERE id = :id");
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            $view_deduction_history_employee = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get deduction history with user info
            $historyStmt = $pdo->prepare("
                SELECT edh.*, u.firstname as changed_by_firstname, u.lastname as changed_by_lastname
                FROM employee_deductions_history edh
                JOIN users u ON edh.changed_by = u.id
                WHERE edh.employee_id = :employee_id
                ORDER BY edh.changed_at DESC
            ");
            $historyStmt->bindParam(':employee_id', $id);
            $historyStmt->execute();
            $view_deduction_history = $historyStmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch(PDOException $e) {
            $swal_data = [
                'icon' => 'error',
                'title' => 'Error',
                'text' => 'Error fetching deduction history: ' . $e->getMessage()
            ];
        }
        
        // Clear form variables after processing deduction history request
        $employee_id = $firstname = $middlename = $lastname = $suffix = $address = $contact_number = $birth_date = $marital_status = $position = $hire_date = $daily_wage = $effectivity_date = '';
    }
    else {
        // It's a new employee registration
        $employee_id = trim($_POST['employee_id']);
        $firstname = trim($_POST['firstname']);
        $middlename = trim($_POST['middlename']);
        $lastname = trim($_POST['lastname']);
        $suffix = trim($_POST['suffix']);
        $address = trim($_POST['address']);
        $contact_number = trim($_POST['contact_number']);
        $birth_date = trim($_POST['birth_date']);
        $marital_status = trim($_POST['marital_status']);
        $position = trim($_POST['position']);
        $hire_date = trim($_POST['hire_date']);
        $daily_wage = isset($_POST['daily_wage']) ? trim($_POST['daily_wage']) : 0.00;
        $effectivity_date = isset($_POST['effectivity_date']) ? trim($_POST['effectivity_date']) : date('Y-m-d');
        
        // Get deduction values
        $cash_advance_amount = isset($_POST['cash_advance_amount']) ? floatval($_POST['cash_advance_amount']) : 0;
        $cash_advance_from = isset($_POST['cash_advance_from']) ? trim($_POST['cash_advance_from']) : null;
        $cash_advance_to = isset($_POST['cash_advance_to']) ? trim($_POST['cash_advance_to']) : null;
        $cash_advance_notes = isset($_POST['cash_advance_notes']) ? trim($_POST['cash_advance_notes']) : '';
        
        $sss_amount = isset($_POST['sss_amount']) ? floatval($_POST['sss_amount']) : 0;
        $sss_effectivity = isset($_POST['sss_effectivity']) ? trim($_POST['sss_effectivity']) : null;
        $sss_notes = isset($_POST['sss_notes']) ? trim($_POST['sss_notes']) : '';
        
        $pagibig_amount = isset($_POST['pagibig_amount']) ? floatval($_POST['pagibig_amount']) : 0;
        $pagibig_effectivity = isset($_POST['pagibig_effectivity']) ? trim($_POST['pagibig_effectivity']) : null;
        $pagibig_notes = isset($_POST['pagibig_notes']) ? trim($_POST['pagibig_notes']) : '';
        
        $philhealth_amount = isset($_POST['philhealth_amount']) ? floatval($_POST['philhealth_amount']) : 0;
        $philhealth_effectivity = isset($_POST['philhealth_effectivity']) ? trim($_POST['philhealth_effectivity']) : null;
        $philhealth_notes = isset($_POST['philhealth_notes']) ? trim($_POST['philhealth_notes']) : '';
        
        // Basic validation
        if (empty($employee_id)) {
            $swal_data = [
                'icon' => 'error',
                'title' => 'Error',
                'text' => 'Employee ID is required.'
            ];
        } elseif (empty($firstname)) {
            $swal_data = [
                'icon' => 'error',
                'title' => 'Error',
                'text' => 'First name is required.'
            ];
        } elseif (empty($lastname)) {
            $swal_data = [
                'icon' => 'error',
                'title' => 'Error',
                'text' => 'Last name is required.'
            ];
        } elseif (empty($position)) {
            $swal_data = [
                'icon' => 'error',
                'title' => 'Error',
                'text' => 'Position is required.'
            ];
        } elseif (empty($hire_date)) {
            $swal_data = [
                'icon' => 'error',
                'title' => 'Error',
                'text' => 'Hire date is required.'
            ];
        } elseif (!is_numeric($daily_wage) || $daily_wage < 0) {
            $swal_data = [
                'icon' => 'error',
                'title' => 'Error',
                'text' => 'Daily wage must be a valid positive number.'
            ];
        } elseif (empty($effectivity_date)) {
            $swal_data = [
                'icon' => 'error',
                'title' => 'Error',
                'text' => 'Effectivity date is required.'
            ];
        } else {
            try {
                // Check if employee ID already exists
                $checkStmt = $pdo->prepare("SELECT id FROM employee WHERE employee_id = :employee_id");
                $checkStmt->bindParam(':employee_id', $employee_id);
                $checkStmt->execute();
                
                if ($checkStmt->rowCount() > 0) {
                    $swal_data = [
                        'icon' => 'error',
                        'title' => 'Error',
                        'text' => 'Employee ID already exists.'
                    ];
                } else {
                    // Begin transaction
                    $pdo->beginTransaction();
                    
                    // Insert new employee
                    $insertStmt = $pdo->prepare("INSERT INTO employee (employee_id, firstname, middlename, lastname, suffix, address, contact_number, birth_date, marital_status, position, hire_date, daily_wage) 
                                               VALUES (:employee_id, :firstname, :middlename, :lastname, :suffix, :address, :contact_number, :birth_date, :marital_status, :position, :hire_date, :daily_wage)");
                    $insertStmt->bindParam(':employee_id', $employee_id);
                    $insertStmt->bindParam(':firstname', $firstname);
                    $insertStmt->bindParam(':middlename', $middlename);
                    $insertStmt->bindParam(':lastname', $lastname);
                    $insertStmt->bindParam(':suffix', $suffix);
                    $insertStmt->bindParam(':address', $address);
                    $insertStmt->bindParam(':contact_number', $contact_number);
                    $insertStmt->bindParam(':birth_date', $birth_date);
                    $insertStmt->bindParam(':marital_status', $marital_status);
                    $insertStmt->bindParam(':position', $position);
                    $insertStmt->bindParam(':hire_date', $hire_date);
                    $insertStmt->bindParam(':daily_wage', $daily_wage);
                    
                    if ($insertStmt->execute()) {
                        $new_employee_id = $pdo->lastInsertId();
                        
                        // Record initial wage in history with effectivity date
                        $historyStmt = $pdo->prepare("INSERT INTO wage_history (employee_id, old_wage, new_wage, change_amount, change_percentage, change_type, changed_by, change_reason, effectivity_date) 
                                                    VALUES (:employee_id, 0, :new_wage, :change_amount, 100, 'increase', :changed_by, 'Initial wage setting', :effectivity_date)");
                        $historyStmt->bindParam(':employee_id', $new_employee_id);
                        $historyStmt->bindParam(':new_wage', $daily_wage);
                        $historyStmt->bindParam(':change_amount', $daily_wage);
                        $historyStmt->bindParam(':changed_by', $_SESSION['user_id']);
                        $historyStmt->bindParam(':effectivity_date', $effectivity_date);
                        $historyStmt->execute();
                        
                        // Insert Cash Advance if provided
                        if ($cash_advance_amount > 0) {
                            $caStmt = $pdo->prepare("INSERT INTO employee_deductions 
                                (employee_id, deduction_type, amount, from_date, to_date, notes, created_by) 
                                VALUES (:employee_id, 'cash_advance', :amount, :from_date, :to_date, :notes, :created_by)");
                            $caStmt->bindParam(':employee_id', $new_employee_id);
                            $caStmt->bindParam(':amount', $cash_advance_amount);
                            $caStmt->bindParam(':from_date', $cash_advance_from);
                            $caStmt->bindParam(':to_date', $cash_advance_to);
                            $caStmt->bindParam(':notes', $cash_advance_notes);
                            $caStmt->bindParam(':created_by', $_SESSION['user_id']);
                            $caStmt->execute();
                            
                            // Record initial cash advance history
                            $caHistoryStmt = $pdo->prepare("
                                INSERT INTO employee_deductions_history 
                                (employee_id, deduction_type, old_amount, new_amount, change_amount, change_percentage, change_type,
                                 old_from_date, new_from_date, old_to_date, new_to_date, old_notes, new_notes, changed_by, change_reason) 
                                VALUES (:employee_id, 'cash_advance', 0, :amount, :amount, 100, 'increase',
                                        NULL, :from_date, NULL, :to_date, '', :notes, :changed_by, 'Initial cash advance setting')
                            ");
                            $caHistoryStmt->bindParam(':employee_id', $new_employee_id);
                            $caHistoryStmt->bindParam(':amount', $cash_advance_amount);
                            $caHistoryStmt->bindParam(':from_date', $cash_advance_from);
                            $caHistoryStmt->bindParam(':to_date', $cash_advance_to);
                            $caHistoryStmt->bindParam(':notes', $cash_advance_notes);
                            $caHistoryStmt->bindParam(':changed_by', $_SESSION['user_id']);
                            $caHistoryStmt->execute();
                        }
                        
                        // Insert SSS if provided
                        if ($sss_amount > 0) {
                            $sssStmt = $pdo->prepare("INSERT INTO employee_deductions 
                                (employee_id, deduction_type, amount, effectivity_date, notes, created_by) 
                                VALUES (:employee_id, 'sss', :amount, :effectivity_date, :notes, :created_by)");
                            $sssStmt->bindParam(':employee_id', $new_employee_id);
                            $sssStmt->bindParam(':amount', $sss_amount);
                            $sssStmt->bindParam(':effectivity_date', $sss_effectivity);
                            $sssStmt->bindParam(':notes', $sss_notes);
                            $sssStmt->bindParam(':created_by', $_SESSION['user_id']);
                            $sssStmt->execute();
                            
                            // Record initial SSS history
                            $sssHistoryStmt = $pdo->prepare("
                                INSERT INTO employee_deductions_history 
                                (employee_id, deduction_type, old_amount, new_amount, change_amount, change_percentage, change_type,
                                 old_effectivity_date, new_effectivity_date, old_notes, new_notes, changed_by, change_reason) 
                                VALUES (:employee_id, 'sss', 0, :amount, :amount, 100, 'increase',
                                        NULL, :effectivity_date, '', :notes, :changed_by, 'Initial SSS setting')
                            ");
                            $sssHistoryStmt->bindParam(':employee_id', $new_employee_id);
                            $sssHistoryStmt->bindParam(':amount', $sss_amount);
                            $sssHistoryStmt->bindParam(':effectivity_date', $sss_effectivity);
                            $sssHistoryStmt->bindParam(':notes', $sss_notes);
                            $sssHistoryStmt->bindParam(':changed_by', $_SESSION['user_id']);
                            $sssHistoryStmt->execute();
                        }
                        
                        // Insert Pag-IBIG if provided
                        if ($pagibig_amount > 0) {
                            $pagibigStmt = $pdo->prepare("INSERT INTO employee_deductions 
                                (employee_id, deduction_type, amount, effectivity_date, notes, created_by) 
                                VALUES (:employee_id, 'pag_ibig', :amount, :effectivity_date, :notes, :created_by)");
                            $pagibigStmt->bindParam(':employee_id', $new_employee_id);
                            $pagibigStmt->bindParam(':amount', $pagibig_amount);
                            $pagibigStmt->bindParam(':effectivity_date', $pagibig_effectivity);
                            $pagibigStmt->bindParam(':notes', $pagibig_notes);
                            $pagibigStmt->bindParam(':created_by', $_SESSION['user_id']);
                            $pagibigStmt->execute();
                            
                            // Record initial Pag-IBIG history
                            $pagibigHistoryStmt = $pdo->prepare("
                                INSERT INTO employee_deductions_history 
                                (employee_id, deduction_type, old_amount, new_amount, change_amount, change_percentage, change_type,
                                 old_effectivity_date, new_effectivity_date, old_notes, new_notes, changed_by, change_reason) 
                                VALUES (:employee_id, 'pag_ibig', 0, :amount, :amount, 100, 'increase',
                                        NULL, :effectivity_date, '', :notes, :changed_by, 'Initial Pag-IBIG setting')
                            ");
                            $pagibigHistoryStmt->bindParam(':employee_id', $new_employee_id);
                            $pagibigHistoryStmt->bindParam(':amount', $pagibig_amount);
                            $pagibigHistoryStmt->bindParam(':effectivity_date', $pagibig_effectivity);
                            $pagibigHistoryStmt->bindParam(':notes', $pagibig_notes);
                            $pagibigHistoryStmt->bindParam(':changed_by', $_SESSION['user_id']);
                            $pagibigHistoryStmt->execute();
                        }
                        
                        // Insert PhilHealth if provided
                        if ($philhealth_amount > 0) {
                            $philhealthStmt = $pdo->prepare("INSERT INTO employee_deductions 
                                (employee_id, deduction_type, amount, effectivity_date, notes, created_by) 
                                VALUES (:employee_id, 'philhealth', :amount, :effectivity_date, :notes, :created_by)");
                            $philhealthStmt->bindParam(':employee_id', $new_employee_id);
                            $philhealthStmt->bindParam(':amount', $philhealth_amount);
                            $philhealthStmt->bindParam(':effectivity_date', $philhealth_effectivity);
                            $philhealthStmt->bindParam(':notes', $philhealth_notes);
                            $philhealthStmt->bindParam(':created_by', $_SESSION['user_id']);
                            $philhealthStmt->execute();
                            
                            // Record initial PhilHealth history
                            $philhealthHistoryStmt = $pdo->prepare("
                                INSERT INTO employee_deductions_history 
                                (employee_id, deduction_type, old_amount, new_amount, change_amount, change_percentage, change_type,
                                 old_effectivity_date, new_effectivity_date, old_notes, new_notes, changed_by, change_reason) 
                                VALUES (:employee_id, 'philhealth', 0, :amount, :amount, 100, 'increase',
                                        NULL, :effectivity_date, '', :notes, :changed_by, 'Initial PhilHealth setting')
                            ");
                            $philhealthHistoryStmt->bindParam(':employee_id', $new_employee_id);
                            $philhealthHistoryStmt->bindParam(':amount', $philhealth_amount);
                            $philhealthHistoryStmt->bindParam(':effectivity_date', $philhealth_effectivity);
                            $philhealthHistoryStmt->bindParam(':notes', $philhealth_notes);
                            $philhealthHistoryStmt->bindParam(':changed_by', $_SESSION['user_id']);
                            $philhealthHistoryStmt->execute();
                        }
                        
                        // Commit transaction
                        $pdo->commit();
                        
                        $swal_data = [
                            'icon' => 'success',
                            'title' => 'Success',
                            'text' => 'Employee registered successfully with deductions!'
                        ];
                        
                        // Clear form fields
                        $employee_id = $firstname = $middlename = $lastname = $suffix = $address = $contact_number = $birth_date = $marital_status = $position = $hire_date = $daily_wage = $effectivity_date = '';
                    } else {
                        $pdo->rollBack();
                        $swal_data = [
                            'icon' => 'error',
                            'title' => 'Error',
                            'text' => 'Error registering employee. Please try again.'
                        ];
                    }
                }
            } catch(PDOException $e) {
                if (isset($pdo) && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $swal_data = [
                    'icon' => 'error',
                    'title' => 'Database Error',
                    'text' => 'Database error: ' . $e->getMessage()
                ];
            }
        }
    }
}
