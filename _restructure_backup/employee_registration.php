<?php
session_start();
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Database connection
require_once 'includes/db_config.php';

// Process form submission
$message = '';
$message_type = ''; // success or danger
$swal_data = []; // For storing SweetAlert data

// Initialize variables to avoid undefined errors
$employee_id = $firstname = $middlename = $lastname = $suffix = $address = $contact_number = $birth_date = $marital_status = $position = $hire_date = $daily_wage = $effectivity_date = '';

// Initialize deduction variables
$cash_advance_amount = $cash_advance_from = $cash_advance_to = $cash_advance_notes = '';
$sss_amount = $sss_effectivity = $sss_notes = '';
$pagibig_amount = $pagibig_effectivity = $pagibig_notes = '';
$philhealth_amount = $philhealth_effectivity = $philhealth_notes = '';

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

// Fetch all employees for the table with their current deductions
try {
    $employeesStmt = $pdo->prepare("
        SELECT e.*, 
            (SELECT amount FROM employee_deductions WHERE employee_id = e.id AND deduction_type = 'cash_advance' LIMIT 1) as cash_advance_amount,
            (SELECT from_date FROM employee_deductions WHERE employee_id = e.id AND deduction_type = 'cash_advance' LIMIT 1) as cash_advance_from,
            (SELECT to_date FROM employee_deductions WHERE employee_id = e.id AND deduction_type = 'cash_advance' LIMIT 1) as cash_advance_to,
            (SELECT amount FROM employee_deductions WHERE employee_id = e.id AND deduction_type = 'sss' LIMIT 1) as sss_amount,
            (SELECT effectivity_date FROM employee_deductions WHERE employee_id = e.id AND deduction_type = 'sss' LIMIT 1) as sss_effectivity,
            (SELECT amount FROM employee_deductions WHERE employee_id = e.id AND deduction_type = 'pag_ibig' LIMIT 1) as pagibig_amount,
            (SELECT effectivity_date FROM employee_deductions WHERE employee_id = e.id AND deduction_type = 'pag_ibig' LIMIT 1) as pagibig_effectivity,
            (SELECT amount FROM employee_deductions WHERE employee_id = e.id AND deduction_type = 'philhealth' LIMIT 1) as philhealth_amount,
            (SELECT effectivity_date FROM employee_deductions WHERE employee_id = e.id AND deduction_type = 'philhealth' LIMIT 1) as philhealth_effectivity
        FROM employee e 
        ORDER BY e.created_at DESC
    ");
    $employeesStmt->execute();
    $employees = $employeesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $employees = [];
    $employee_error = "Error fetching employees: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>Employee Registration - OCP Construction</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" />
        <link href="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/style.min.css" rel="stylesheet" />
        <link href="css/styles.css" rel="stylesheet" />
        <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <style>
            .btn-group .btn:last-child {
                margin-right: 5px;
            }
            .deduction-badge {
                font-size: 0.8rem;
                padding: 0.2rem 0.4rem;
                margin: 0.1rem;
                display: inline-block;
                cursor: pointer;
            }
            .deductions-container {
                max-width: 300px;
            }
            .history-tooltip {
                position: relative;
                display: inline-block;
            }
            .history-tooltip .tooltip-text {
                visibility: hidden;
                width: 200px;
                background-color: #555;
                color: #fff;
                text-align: center;
                border-radius: 6px;
                padding: 5px;
                position: absolute;
                z-index: 1;
                bottom: 125%;
                left: 50%;
                margin-left: -100px;
                opacity: 0;
                transition: opacity 0.3s;
            }
            .history-tooltip:hover .tooltip-text {
                visibility: visible;
                opacity: 1;
            }
            .deduction-section {
                border: 1px solid #dee2e6;
                border-radius: 0.25rem;
                padding: 1rem;
                margin-bottom: 1rem;
                background-color: #f8f9fa;
            }
            .deduction-section h6 {
                margin-top: 0;
                margin-bottom: 1rem;
                color: #495057;
                font-weight: 600;
            }
            .dropdown-item i {
                width: 20px;
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
                        <h1 class="mt-4">Employee Registration</h1>
                        <ol class="breadcrumb mb-4">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Employee Registration</li>
                        </ol>
                        
                        <!-- Employee Table -->
                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fas fa-users me-1"></i>
                                    Employee List
                                </div>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEmployeeModal">
                                    <i class="fas fa-user-plus me-1"></i> Add Employee
                                </button>
                            </div>
                            <div class="card-body">
                                <?php if (isset($employee_error)): ?>
                                    <div class="alert alert-danger"><?php echo $employee_error; ?></div>
                                <?php elseif (empty($employees)): ?>
                                    <div class="alert alert-info">No employees found.</div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-striped table-bordered" id="employeeTable">
                                            <thead>
                                                <tr>
                                                    <th>Employee ID</th>
                                                    <th>Name</th>
                                                    <th>Position</th>
                                                    <th>Daily Wage</th>
                                                    <th>Deductions</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($employees as $employee): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($employee['employee_id']); ?></td>
                                                        <td>
                                                            <?php 
                                                                echo htmlspecialchars($employee['lastname']) . ', ' . 
                                                                    htmlspecialchars($employee['firstname']);
                                                                if (!empty($employee['middlename'])) {
                                                                    echo ' ' . substr(htmlspecialchars($employee['middlename']), 0, 1) . '.';
                                                                }
                                                                if (!empty($employee['suffix'])) {
                                                                    echo ' ' . htmlspecialchars($employee['suffix']);
                                                                }
                                                            ?>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($employee['position']); ?></td>
                                                        <td>₱<?php echo number_format($employee['daily_wage'], 2); ?></td>
                                                        <td>
                                                            <div class="deductions-container">
                                                                <?php if (!empty($employee['cash_advance_amount'])): ?>
                                                                    <span class="badge bg-info deduction-badge history-tooltip" 
                                                                        title="From: <?php echo $employee['cash_advance_from'] ? date('M d, Y', strtotime($employee['cash_advance_from'])) : 'N/A'; ?> 
                                                                                To: <?php echo $employee['cash_advance_to'] ? date('M d, Y', strtotime($employee['cash_advance_to'])) : 'N/A'; ?>">
                                                                        CA: ₱<?php echo number_format($employee['cash_advance_amount'], 2); ?>
                                                                    </span>
                                                                <?php endif; ?>
                                                                
                                                                <?php if (!empty($employee['sss_amount'])): ?>
                                                                    <span class="badge bg-warning text-dark deduction-badge" 
                                                                        title="Effectivity: <?php echo $employee['sss_effectivity'] ? date('M d, Y', strtotime($employee['sss_effectivity'])) : 'N/A'; ?>">
                                                                        SSS: ₱<?php echo number_format($employee['sss_amount'], 2); ?>
                                                                    </span>
                                                                <?php endif; ?>
                                                                
                                                                <?php if (!empty($employee['pagibig_amount'])): ?>
                                                                    <span class="badge bg-success deduction-badge" 
                                                                        title="Effectivity: <?php echo $employee['pagibig_effectivity'] ? date('M d, Y', strtotime($employee['pagibig_effectivity'])) : 'N/A'; ?>">
                                                                        PAG-IBIG: ₱<?php echo number_format($employee['pagibig_amount'], 2); ?>
                                                                    </span>
                                                                <?php endif; ?>
                                                                
                                                                <?php if (!empty($employee['philhealth_amount'])): ?>
                                                                    <span class="badge bg-danger deduction-badge" 
                                                                        title="Effectivity: <?php echo $employee['philhealth_effectivity'] ? date('M d, Y', strtotime($employee['philhealth_effectivity'])) : 'N/A'; ?>">
                                                                        PHILHEALTH: ₱<?php echo number_format($employee['philhealth_amount'], 2); ?>
                                                                    </span>
                                                                <?php endif; ?>
                                                                
                                                                <?php if (empty($employee['cash_advance_amount']) && empty($employee['sss_amount']) && empty($employee['pagibig_amount']) && empty($employee['philhealth_amount'])): ?>
                                                                    <span class="text-muted">No deductions</span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-<?php echo $employee['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                                                <?php echo ucfirst($employee['status']); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <div class="d-flex gap-1" role="group" aria-label="Employee actions">
                                                                <!-- Manage Dropdown - Fixed with consistent styling -->
                                                                <div class="dropdown d-inline-block">
                                                                    <button type="button" class="btn btn-sm btn-primary dropdown-toggle rounded" data-bs-toggle="dropdown" aria-expanded="false">
                                                                        <i class="fas fa-cog"></i> Manage
                                                                    </button>
                                                                    <ul class="dropdown-menu">
                                                                        <li>
                                                                            <form method="POST" class="dropdown-item p-0">
                                                                                <input type="hidden" name="employee_id" value="<?php echo $employee['id']; ?>">
                                                                                <input type="hidden" name="edit_wage" value="1">
                                                                                <button type="submit" class="dropdown-item">
                                                                                    <i class="fas fa-money-bill-wave"></i> Wage
                                                                                </button>
                                                                            </form>
                                                                        </li>
                                                                        <li>
                                                                            <form method="POST" class="dropdown-item p-0">
                                                                                <input type="hidden" name="employee_id" value="<?php echo $employee['id']; ?>">
                                                                                <input type="hidden" name="view_wage_history" value="1">
                                                                                <button type="submit" class="dropdown-item">
                                                                                    <i class="fas fa-chart-line"></i> Wage History
                                                                                </button>
                                                                            </form>
                                                                        </li>
                                                                        <li><hr class="dropdown-divider"></li>
                                                                        <li>
                                                                            <form method="POST" class="dropdown-item p-0">
                                                                                <input type="hidden" name="employee_id" value="<?php echo $employee['id']; ?>">
                                                                                <input type="hidden" name="edit_deductions" value="1">
                                                                                <button type="submit" class="dropdown-item">
                                                                                    <i class="fas fa-calculator"></i> Deductions
                                                                                </button>
                                                                            </form>
                                                                        </li>
                                                                        <li>
                                                                            <form method="POST" class="dropdown-item p-0">
                                                                                <input type="hidden" name="employee_id" value="<?php echo $employee['id']; ?>">
                                                                                <input type="hidden" name="view_deduction_history" value="1">
                                                                                <button type="submit" class="dropdown-item">
                                                                                    <i class="fas fa-history"></i> Deduction History
                                                                                </button>
                                                                            </form>
                                                                        </li>
                                                                    </ul>
                                                                </div>
                                                                
                                                                <!-- View Button -->
                                                                <form method="POST" class="d-inline">
                                                                    <input type="hidden" name="employee_id" value="<?php echo $employee['id']; ?>">
                                                                    <input type="hidden" name="view_employee" value="1">
                                                                    <button type="submit" class="btn btn-sm btn-info rounded" title="View">
                                                                        <i class="fas fa-eye"></i>
                                                                    </button>
                                                                </form>
                                                                
                                                                <!-- Edit Details Button -->
                                                                <form method="POST" class="d-inline">
                                                                    <input type="hidden" name="employee_id" value="<?php echo $employee['id']; ?>">
                                                                    <input type="hidden" name="edit_details" value="1">
                                                                    <button type="submit" class="btn btn-sm btn-warning rounded" title="Edit Details">
                                                                        <i class="fas fa-edit"></i>
                                                                    </button>
                                                                </form>
                                                                
                                                                <!-- Delete Button -->
                                                                <form method="POST" class="d-inline">
                                                                    <input type="hidden" name="employee_id" value="<?php echo $employee['id']; ?>">
                                                                    <input type="hidden" name="delete_employee" value="1">
                                                                    <button type="submit" class="btn btn-sm btn-danger rounded" title="Delete" onclick="return confirmDelete(event)">
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
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </main>
                <?php include 'includes/footer.php';?>
            </div>
        </div>

        <!-- Add Employee Modal -->
        <div class="modal fade" id="addEmployeeModal" tabindex="-1" aria-labelledby="addEmployeeModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addEmployeeModalLabel">Register New Employee</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="">
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="modal_employee_id" name="employee_id" 
                                               value="<?php echo htmlspecialchars($employee_id); ?>" 
                                               required maxlength="50" placeholder="Employee ID">
                                        <label for="modal_employee_id" class="form-label">Employee ID <span class="text-danger">*</span></label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="date" class="form-control" id="modal_hire_date" name="hire_date" 
                                               value="<?php echo htmlspecialchars($hire_date); ?>" 
                                               required placeholder="Hire Date">
                                        <label for="modal_hire_date" class="form-label">Hire Date <span class="text-danger">*</span></label>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="modal_lastname" name="lastname" 
                                               value="<?php echo htmlspecialchars($lastname); ?>" 
                                               required maxlength="100" placeholder="Last Name">
                                        <label for="modal_lastname" class="form-label">Last Name <span class="text-danger">*</span></label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="modal_firstname" name="firstname" 
                                               value="<?php echo htmlspecialchars($firstname); ?>" 
                                               required maxlength="100" placeholder="First Name">
                                        <label for="modal_firstname" class="form-label">First Name <span class="text-danger">*</span></label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="modal_middlename" name="middlename" 
                                               value="<?php echo htmlspecialchars($middlename); ?>" 
                                               maxlength="100" placeholder="Middle Name">
                                        <label for="modal_middlename" class="form-label">Middle Name</label>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <select class="form-select" id="modal_suffix" name="suffix" aria-label="Suffix">
                                            <option value="">Select Suffix</option>
                                            <option value="Jr." <?php echo ($suffix == 'Jr.') ? 'selected' : ''; ?>>Jr.</option>
                                            <option value="Sr." <?php echo ($suffix == 'Sr.') ? 'selected' : ''; ?>>Sr.</option>
                                            <option value="II" <?php echo ($suffix == 'II') ? 'selected' : ''; ?>>II</option>
                                            <option value="III" <?php echo ($suffix == 'III') ? 'selected' : ''; ?>>III</option>
                                            <option value="IV" <?php echo ($suffix == 'IV') ? 'selected' : ''; ?>>IV</option>
                                        </select>
                                        <label for="modal_suffix" class="form-label">Suffix</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <select class="form-select" id="modal_marital_status" name="marital_status" aria-label="Marital Status">
                                            <option value="">Select Marital Status</option>
                                            <option value="Single" <?php echo ($marital_status == 'Single') ? 'selected' : ''; ?>>Single</option>
                                            <option value="Married" <?php echo ($marital_status == 'Married') ? 'selected' : ''; ?>>Married</option>
                                            <option value="Divorced" <?php echo ($marital_status == 'Divorced') ? 'selected' : ''; ?>>Divorced</option>
                                            <option value="Widowed" <?php echo ($marital_status == 'Widowed') ? 'selected' : ''; ?>>Widowed</option>
                                        </select>
                                        <label for="modal_marital_status" class="form-label">Marital Status</label>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="modal_contact_number" name="contact_number" 
                                               value="<?php echo htmlspecialchars($contact_number); ?>" 
                                               maxlength="20" placeholder="Contact Number">
                                        <label for="modal_contact_number" class="form-label">Contact Number</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <input type="date" class="form-control" id="modal_birth_date" name="birth_date" 
                                               value="<?php echo htmlspecialchars($birth_date); ?>" placeholder="Birth Date">
                                        <label for="modal_birth_date" class="form-label">Birth Date</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <input type="number" step="0.01" min="0" class="form-control" id="modal_daily_wage" name="daily_wage" 
                                               value="<?php echo htmlspecialchars($daily_wage); ?>" required placeholder="Daily Wage">
                                        <label for="modal_daily_wage" class="form-label">Daily Wage <span class="text-danger">*</span></label>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="date" class="form-control" id="modal_effectivity_date" name="effectivity_date" 
                                               value="<?php echo !empty($effectivity_date) ? htmlspecialchars($effectivity_date) : date('Y-m-d'); ?>" 
                                               required placeholder="Effectivity Date">
                                        <label for="modal_effectivity_date" class="form-label">Wage Effectivity Date <span class="text-danger">*</span></label>
                                    </div>
                                </div>
                                
                                <div class="col-12">
                                    <div class="form-floating">
                                        <textarea class="form-control" id="modal_address" name="address" rows="3" placeholder="Address"><?php echo htmlspecialchars($address); ?></textarea>
                                        <label for="modal_address" class="form-label">Address</label>
                                    </div>
                                </div>
                                
                                <div class="col-12">
                                    <div class="form-floating">
                                        <select class="form-select" id="modal_position" name="position" required aria-label="Position">
                                            <option value="">Select Position</option>
                                            <option value="Operator" <?php echo ($position == 'Operator') ? 'selected' : ''; ?>>Operator</option>
                                            <option value="Driver" <?php echo ($position == 'Driver') ? 'selected' : ''; ?>>Driver</option>
                                            <option value="Chief Mechanic" <?php echo ($position == 'Chief Mechanic') ? 'selected' : ''; ?>>Chief Mechanic</option>
                                            <option value="Mechanic" <?php echo ($position == 'Mechanic') ? 'selected' : ''; ?>>Mechanic</option>
                                            <option value="Welder" <?php echo ($position == 'Welder') ? 'selected' : ''; ?>>Welder</option>
                                            <option value="Foreman" <?php echo ($position == 'Foreman') ? 'selected' : ''; ?>>Foreman</option>
                                            <option value="Skilled" <?php echo ($position == 'Skilled') ? 'selected' : ''; ?>>Skilled</option>
                                            <option value="Helper" <?php echo ($position == 'Helper') ? 'selected' : ''; ?>>Helper</option>
                                            <option value="Labor" <?php echo ($position == 'Labor') ? 'selected' : ''; ?>>Labor</option>
                                            <option value="Cook | Office Helper" <?php echo ($position == 'Cook | Office Helper') ? 'selected' : ''; ?>>Cook | Office Helper</option>
                                            <option value="Guard" <?php echo ($position == 'Guard') ? 'selected' : ''; ?>>Guard</option>
                                            <option value="Human Resources Officer" <?php echo ($position == 'Human Resources Officer') ? 'selected' : ''; ?>>Human Resources Officer</option>
                                            <option value="Document Controller" <?php echo ($position == 'Document Controller') ? 'selected' : ''; ?>>Document Controller</option>
                                            <option value="Purchasing Officer" <?php echo ($position == 'Purchasing Officer') ? 'selected' : ''; ?>>Purchasing Officer</option>
                                            <option value="Disbursing Officer" <?php echo ($position == 'Disbursing Officer') ? 'selected' : ''; ?>>Disbursing Officer</option>
                                            <option value="Warehouseman" <?php echo ($position == 'Warehouseman') ? 'selected' : ''; ?>>Warehouseman</option>
                                            <option value="Site Engineer" <?php echo ($position == 'Site Engineer') ? 'selected' : ''; ?>>Site Engineer</option>
                                            <option value="Liaison Officer" <?php echo ($position == 'Liaison Officer') ? 'selected' : ''; ?>>Liaison Officer</option>
                                            <option value="Assistant Project Manager" <?php echo ($position == 'Assistant Project Manager') ? 'selected' : ''; ?>>Assistant Project Manager</option>
                                            <option value="Checker" <?php echo ($position == 'Checker') ? 'Checker' : ''; ?>>Checker</option>
                                        </select>
                                        <label for="modal_position" class="form-label">Position <span class="text-danger">*</span></label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Register Employee</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- View Employee Modal -->
        <?php if (isset($view_employee)): ?>
        <div class="modal fade show" id="viewEmployeeModal" tabindex="-1" aria-labelledby="viewEmployeeModalLabel" aria-hidden="false" style="display: block; background: rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="viewEmployeeModalLabel">Employee Details</h5>
                        <a href="employee_registration.php" class="btn-close" aria-label="Close"></a>
                    </div>
                    <div class="modal-body">
                        <!-- Removed tabs - only showing personal information -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Employee ID</label>
                                    <p class="form-control-static"><?php echo htmlspecialchars($view_employee['employee_id']); ?></p>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Name</label>
                                    <p class="form-control-static">
                                        <?php 
                                            echo htmlspecialchars($view_employee['lastname']) . ', ' . 
                                                 htmlspecialchars($view_employee['firstname']);
                                            if (!empty($view_employee['middlename'])) {
                                                echo ' ' . substr(htmlspecialchars($view_employee['middlename']), 0, 1) . '.';
                                            }
                                            if (!empty($view_employee['suffix'])) {
                                                echo ' ' . htmlspecialchars($view_employee['suffix']);
                                            }
                                        ?>
                                    </p>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Contact Number</label>
                                    <p class="form-control-static"><?php echo !empty($view_employee['contact_number']) ? htmlspecialchars($view_employee['contact_number']) : 'N/A'; ?></p>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Birth Date</label>
                                    <p class="form-control-static"><?php echo !empty($view_employee['birth_date']) ? date('M d, Y', strtotime($view_employee['birth_date'])) : 'N/A'; ?></p>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Marital Status</label>
                                    <p class="form-control-static"><?php echo !empty($view_employee['marital_status']) ? htmlspecialchars($view_employee['marital_status']) : 'N/A'; ?></p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Position</label>
                                    <p class="form-control-static"><?php echo htmlspecialchars($view_employee['position']); ?></p>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Hire Date</label>
                                    <p class="form-control-static"><?php echo date('M d, Y', strtotime($view_employee['hire_date'])); ?></p>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Daily Wage</label>
                                    <p class="form-control-static">₱<?php echo number_format($view_employee['daily_wage'], 2); ?></p>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Status</label>
                                    <p class="form-control-static">
                                        <span class="badge bg-<?php echo $view_employee['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                            <?php echo ucfirst($view_employee['status']); ?>
                                        </span>
                                    </p>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Address</label>
                                    <p class="form-control-static"><?php echo !empty($view_employee['address']) ? htmlspecialchars($view_employee['address']) : 'N/A'; ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <a href="employee_registration.php" class="btn btn-secondary">Close</a>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Edit Employee Details Modal -->
        <?php if (isset($edit_employee_details)): ?>
        <div class="modal fade show" id="editEmployeeModal" tabindex="-1" aria-labelledby="editEmployeeModalLabel" aria-hidden="false" style="display: block; background: rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editEmployeeModalLabel">Edit Employee Details</h5>
                        <a href="employee_registration.php" class="btn-close" aria-label="Close"></a>
                    </div>
                    <form method="POST" action="">
                        <input type="hidden" name="update_employee_details" value="1">
                        <input type="hidden" name="employee_id" value="<?php echo $edit_employee_details['id']; ?>">
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="edit_employee_id" name="employee_id_field" 
                                               value="<?php echo htmlspecialchars($edit_employee_details['employee_id']); ?>" 
                                               required maxlength="50" placeholder="Employee ID">
                                        <label for="edit_employee_id" class="form-label">Employee ID <span class="text-danger">*</span></label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="date" class="form-control" id="edit_hire_date" name="hire_date" 
                                               value="<?php echo htmlspecialchars($edit_employee_details['hire_date']); ?>" 
                                               required placeholder="Hire Date">
                                        <label for="edit_hire_date" class="form-label">Hire Date <span class="text-danger">*</span></label>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="edit_lastname" name="lastname" 
                                               value="<?php echo htmlspecialchars($edit_employee_details['lastname']); ?>" 
                                               required maxlength="100" placeholder="Last Name">
                                        <label for="edit_lastname" class="form-label">Last Name <span class="text-danger">*</span></label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="edit_firstname" name="firstname" 
                                               value="<?php echo htmlspecialchars($edit_employee_details['firstname']); ?>" 
                                               required maxlength="100" placeholder="First Name">
                                        <label for="edit_firstname" class="form-label">First Name <span class="text-danger">*</span></label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="edit_middlename" name="middlename" 
                                               value="<?php echo htmlspecialchars($edit_employee_details['middlename']); ?>" 
                                               maxlength="100" placeholder="Middle Name">
                                        <label for="edit_middlename" class="form-label">Middle Name</label>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <select class="form-select" id="edit_suffix" name="suffix" aria-label="Suffix">
                                            <option value="">Select Suffix</option>
                                            <option value="Jr." <?php echo ($edit_employee_details['suffix'] == 'Jr.') ? 'selected' : ''; ?>>Jr.</option>
                                            <option value="Sr." <?php echo ($edit_employee_details['suffix'] == 'Sr.') ? 'selected' : ''; ?>>Sr.</option>
                                            <option value="II" <?php echo ($edit_employee_details['suffix'] == 'II') ? 'selected' : ''; ?>>II</option>
                                            <option value="III" <?php echo ($edit_employee_details['suffix'] == 'III') ? 'selected' : ''; ?>>III</option>
                                            <option value="IV" <?php echo ($edit_employee_details['suffix'] == 'IV') ? 'selected' : ''; ?>>IV</option>
                                        </select>
                                        <label for="edit_suffix" class="form-label">Suffix</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <select class="form-select" id="edit_marital_status" name="marital_status" aria-label="Marital Status">
                                            <option value="">Select Marital Status</option>
                                            <option value="Single" <?php echo ($edit_employee_details['marital_status'] == 'Single') ? 'selected' : ''; ?>>Single</option>
                                            <option value="Married" <?php echo ($edit_employee_details['marital_status'] == 'Married') ? 'selected' : ''; ?>>Married</option>
                                            <option value="Divorced" <?php echo ($edit_employee_details['marital_status'] == 'Divorced') ? 'selected' : ''; ?>>Divorced</option>
                                            <option value="Widowed" <?php echo ($edit_employee_details['marital_status'] == 'Widowed') ? 'selected' : ''; ?>>Widowed</option>
                                        </select>
                                        <label for="edit_marital_status" class="form-label">Marital Status</label>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="edit_contact_number" name="contact_number" 
                                               value="<?php echo htmlspecialchars($edit_employee_details['contact_number']); ?>" 
                                               maxlength="20" placeholder="Contact Number">
                                        <label for="edit_contact_number" class="form-label">Contact Number</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <input type="date" class="form-control" id="edit_birth_date" name="birth_date" 
                                               value="<?php echo htmlspecialchars($edit_employee_details['birth_date']); ?>" placeholder="Birth Date">
                                        <label for="edit_birth_date" class="form-label">Birth Date</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <select class="form-select" id="edit_status" name="status" required aria-label="Status">
                                            <option value="active" <?php echo ($edit_employee_details['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                                            <option value="inactive" <?php echo ($edit_employee_details['status'] == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                        </select>
                                        <label for="edit_status" class="form-label">Status</label>
                                    </div>
                                </div>
                                
                                <div class="col-12">
                                    <div class="form-floating">
                                        <textarea class="form-control" id="edit_address" name="address" rows="3" placeholder="Address"><?php echo htmlspecialchars($edit_employee_details['address']); ?></textarea>
                                        <label for="edit_address" class="form-label">Address</label>
                                    </div>
                                </div>
                                
                                <div class="col-12">
                                    <div class="form-floating">
                                        <select class="form-select" id="edit_position" name="position" required aria-label="Position">
                                            <option value="">Select Position</option>
                                            <option value="Operator" <?php echo ($edit_employee_details['position'] == 'Operator') ? 'selected' : ''; ?>>Operator</option>
                                            <option value="Driver" <?php echo ($edit_employee_details['position'] == 'Driver') ? 'selected' : ''; ?>>Driver</option>
                                            <option value="Mechanic" <?php echo ($edit_employee_details['position'] == 'Mechanic') ? 'selected' : ''; ?>>Mechanic</option>
                                            <option value="Welder" <?php echo ($edit_employee_details['position'] == 'Welder') ? 'selected' : ''; ?>>Welder</option>
                                            <option value="Vulcanizer" <?php echo ($edit_employee_details['position'] == 'Vulcanizer') ? 'selected' : ''; ?>>Vulcanizer</option>
                                            <option value="Building Electrician" <?php echo ($edit_employee_details['position'] == 'Building Electrician') ? 'selected' : ''; ?>>Building Electrician</option>
                                            <option value="Auto Electrician" <?php echo ($edit_employee_details['position'] == 'Auto Electrician') ? 'selected' : ''; ?>>Auto Electrician</option>
                                            <option value="Foreman" <?php echo ($edit_employee_details['position'] == 'Foreman') ? 'selected' : ''; ?>>Foreman</option>
                                            <option value="Skilled" <?php echo ($edit_employee_details['position'] == 'Skilled') ? 'selected' : ''; ?>>Skilled</option>
                                            <option value="Helper" <?php echo ($edit_employee_details['position'] == 'Helper') ? 'selected' : ''; ?>>Helper</option>
                                            <option value="Labor" <?php echo ($edit_employee_details['position'] == 'Labor') ? 'selected' : ''; ?>>Labor</option>
                                            <option value="Flockman" <?php echo ($edit_employee_details['position'] == 'Flockman') ? 'selected' : ''; ?>>Flockman</option>
                                            <option value="Cook | Office Helper" <?php echo ($edit_employee_details['position'] == 'Cook | Office Helper') ? 'selected' : ''; ?>>Cook | Office Helper</option>
                                            <option value="Painter" <?php echo ($edit_employee_details['position'] == 'Painter') ? 'selected' : ''; ?>>Painter</option>
                                        </select>
                                        <label for="edit_position" class="form-label">Position <span class="text-danger">*</span></label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <a href="employee_registration.php" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">Update Details</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Edit Daily Wage Modal -->
        <?php if (isset($edit_employee_wage)): ?>
        <div class="modal fade show" id="editWageModal" tabindex="-1" aria-labelledby="editWageModalLabel" aria-hidden="false" style="display: block; background: rgba(0,0,0,0.5);">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editWageModalLabel">Daily Wage</h5>
                        <a href="employee_registration.php" class="btn-close" aria-label="Close"></a>
                    </div>
                    <form method="POST" action="">
                        <input type="hidden" name="update_employee_wage" value="1">
                        <input type="hidden" name="employee_id" value="<?php echo $edit_employee_wage['id']; ?>">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label"><strong>Employee</strong></label>
                                <p class="form-control-static">
                                    <?php 
                                        echo htmlspecialchars($edit_employee_wage['lastname']) . ', ' . 
                                             htmlspecialchars($edit_employee_wage['firstname']);
                                        if (!empty($edit_employee_wage['middlename'])) {
                                            echo ' ' . substr(htmlspecialchars($edit_employee_wage['middlename']), 0, 1) . '.';
                                        }
                                        if (!empty($edit_employee_wage['suffix'])) {
                                            echo ' ' . htmlspecialchars($edit_employee_wage['suffix']);
                                        }
                                    ?>
                                </p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><strong>Position</strong></label>
                                <p class="form-control-static"><?php echo htmlspecialchars($edit_employee_wage['position']); ?></p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><strong>Current Daily Wage</strong></label>
                                <p class="form-control-static">₱<?php echo number_format($edit_employee_wage['daily_wage'], 2); ?></p>
                            </div>
                            <div class="form-floating mb-3">
                                <input type="number" step="0.01" min="0" class="form-control" id="edit_daily_wage" name="daily_wage" 
                                       value="<?php echo htmlspecialchars($edit_employee_wage['daily_wage']); ?>" required placeholder="New Daily Wage">
                                <label for="edit_daily_wage" class="form-label">New Daily Wage</label>
                            </div>
                            <div class="form-floating mb-3">
                                <input type="date" class="form-control" id="edit_effectivity_date" name="effectivity_date" 
                                       value="<?php echo date('Y-m-d'); ?>" required placeholder="Effectivity Date">
                                <label for="edit_effectivity_date" class="form-label">Effectivity Date <span class="text-danger">*</span></label>
                            </div>
                            <div class="form-floating mb-3">
                                <textarea class="form-control" id="change_reason" name="change_reason" rows="3" placeholder="Enter reason for wage change"></textarea>
                                <label for="change_reason" class="form-label">Reason for Change</label>
                            </div>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <small>Note: If you edit the wage multiple times today, only the last edit will be saved in the history.</small>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <a href="employee_registration.php" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">Update Wage</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Edit Deductions Modal -->
        <?php if (isset($edit_employee_deductions)): ?>
        <div class="modal fade show" id="editDeductionsModal" tabindex="-1" aria-labelledby="editDeductionsModalLabel" aria-hidden="false" style="display: block; background: rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editDeductionsModalLabel">Employee Deductions</h5>
                        <a href="employee_registration.php" class="btn-close" aria-label="Close"></a>
                    </div>
                    <form method="POST" action="">
                        <input type="hidden" name="update_employee_deductions" value="1">
                        <input type="hidden" name="employee_id" value="<?php echo $edit_employee_deductions['id']; ?>">
                        <div class="modal-body">
                            <div class="mb-3">
                                <h6>Employee: 
                                    <?php 
                                        echo htmlspecialchars($edit_employee_deductions['lastname']) . ', ' . 
                                             htmlspecialchars($edit_employee_deductions['firstname']);
                                        if (!empty($edit_employee_deductions['middlename'])) {
                                            echo ' ' . substr(htmlspecialchars($edit_employee_deductions['middlename']), 0, 1) . '.';
                                        }
                                        if (!empty($edit_employee_deductions['suffix'])) {
                                            echo ' ' . htmlspecialchars($edit_employee_deductions['suffix']);
                                        }
                                    ?>
                                </h6>
                                <p class="text-muted">Position: <?php echo htmlspecialchars($edit_employee_deductions['position']); ?></p>
                            </div>
                            
                            <!-- Deduction Type Selector -->
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <select class="form-select" id="deduction_type_selector" onchange="showDeductionSection(this.value)">
                                            <option value="">-- Select Deduction Type --</option>
                                            <option value="cash_advance">Cash Advance</option>
                                            <option value="sss">SSS Contribution</option>
                                            <option value="pag_ibig">Pag-IBIG Contribution</option>
                                            <option value="philhealth">PhilHealth Contribution</option>
                                        </select>
                                        <label for="deduction_type_selector">Select Deduction Type</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <p class="text-muted mt-2">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Select a deduction type from the dropdown to add or edit its details.
                                    </p>
                                </div>
                            </div>
                            
                            <div class="row g-3">
                                <!-- Cash Advance Section -->
                                <div class="col-12 deduction-section" id="cash_advance_section" style="display: none;">
                                    <h6 class="border-bottom pb-2">Cash Advance Details</h6>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-floating mb-3">
                                                <input type="number" step="0.01" min="0" class="form-control" id="edit_cash_advance_amount" name="cash_advance_amount" 
                                                       value="<?php echo isset($edit_deductions_data['cash_advance']['amount']) ? $edit_deductions_data['cash_advance']['amount'] : ''; ?>" 
                                                       placeholder="Cash Advance Amount">
                                                <label for="edit_cash_advance_amount">Amount</label>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-floating mb-3">
                                                <input type="date" class="form-control" id="edit_cash_advance_from" name="cash_advance_from" 
                                                       value="<?php echo isset($edit_deductions_data['cash_advance']['from_date']) ? $edit_deductions_data['cash_advance']['from_date'] : ''; ?>" 
                                                       placeholder="From Date">
                                                <label for="edit_cash_advance_from">From Date</label>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-floating mb-3">
                                                <input type="date" class="form-control" id="edit_cash_advance_to" name="cash_advance_to" 
                                                       value="<?php echo isset($edit_deductions_data['cash_advance']['to_date']) ? $edit_deductions_data['cash_advance']['to_date'] : ''; ?>" 
                                                       placeholder="To Date">
                                                <label for="edit_cash_advance_to">To Date</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- SSS Section -->
                                <div class="col-12 deduction-section" id="sss_section" style="display: none;">
                                    <h6 class="border-bottom pb-2">SSS Contribution Details</h6>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-floating mb-3">
                                                <input type="number" step="0.01" min="0" class="form-control" id="edit_sss_amount" name="sss_amount" 
                                                       value="<?php echo isset($edit_deductions_data['sss']['amount']) ? $edit_deductions_data['sss']['amount'] : ''; ?>" 
                                                       placeholder="SSS Amount">
                                                <label for="edit_sss_amount">Monthly Contribution</label>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-floating mb-3">
                                                <input type="date" class="form-control" id="edit_sss_effectivity" name="sss_effectivity" 
                                                       value="<?php echo isset($edit_deductions_data['sss']['effectivity_date']) ? $edit_deductions_data['sss']['effectivity_date'] : ''; ?>" 
                                                       placeholder="Effectivity Date">
                                                <label for="edit_sss_effectivity">Effectivity Date</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Pag-IBIG Section -->
                                <div class="col-12 deduction-section" id="pag_ibig_section" style="display: none;">
                                    <h6 class="border-bottom pb-2">Pag-IBIG Contribution Details</h6>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-floating mb-3">
                                                <input type="number" step="0.01" min="0" class="form-control" id="edit_pagibig_amount" name="pagibig_amount" 
                                                       value="<?php echo isset($edit_deductions_data['pag_ibig']['amount']) ? $edit_deductions_data['pag_ibig']['amount'] : ''; ?>" 
                                                       placeholder="Pag-IBIG Amount">
                                                <label for="edit_pagibig_amount">Monthly Contribution</label>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-floating mb-3">
                                                <input type="date" class="form-control" id="edit_pagibig_effectivity" name="pagibig_effectivity" 
                                                       value="<?php echo isset($edit_deductions_data['pag_ibig']['effectivity_date']) ? $edit_deductions_data['pag_ibig']['effectivity_date'] : ''; ?>" 
                                                       placeholder="Effectivity Date">
                                                <label for="edit_pagibig_effectivity">Effectivity Date</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- PhilHealth Section -->
                                <div class="col-12 deduction-section" id="philhealth_section" style="display: none;">
                                    <h6 class="border-bottom pb-2">PhilHealth Contribution Details</h6>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-floating mb-3">
                                                <input type="number" step="0.01" min="0" class="form-control" id="edit_philhealth_amount" name="philhealth_amount" 
                                                       value="<?php echo isset($edit_deductions_data['philhealth']['amount']) ? $edit_deductions_data['philhealth']['amount'] : ''; ?>" 
                                                       placeholder="PhilHealth Amount">
                                                <label for="edit_philhealth_amount">Monthly Contribution</label>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-floating mb-3">
                                                <input type="date" class="form-control" id="edit_philhealth_effectivity" name="philhealth_effectivity" 
                                                       value="<?php echo isset($edit_deductions_data['philhealth']['effectivity_date']) ? $edit_deductions_data['philhealth']['effectivity_date'] : ''; ?>" 
                                                       placeholder="Effectivity Date">
                                                <label for="edit_philhealth_effectivity">Effectivity Date</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Common Fields -->
                                <div class="col-12 mt-3">
                                    <div class="form-floating mb-3">
                                        <textarea class="form-control" id="edit_change_reason" name="change_reason" rows="2" placeholder="Reason for changes"></textarea>
                                        <label for="edit_change_reason">Reason for Changes (optional)</label>
                                    </div>
                                </div>
                                
                                <!-- Current Deductions Summary -->
                                <div class="col-12 mt-3">
                                    <div class="alert alert-info">
                                        <h6 class="alert-heading">Current Deductions Summary</h6>
                                        <div class="row">
                                            <div class="col-md-3">
                                                <strong>CA:</strong> 
                                                <?php echo isset($edit_deductions_data['cash_advance']['amount']) ? '₱' . number_format($edit_deductions_data['cash_advance']['amount'], 2) : 'None'; ?>
                                            </div>
                                            <div class="col-md-3">
                                                <strong>SSS:</strong> 
                                                <?php echo isset($edit_deductions_data['sss']['amount']) ? '₱' . number_format($edit_deductions_data['sss']['amount'], 2) : 'None'; ?>
                                            </div>
                                            <div class="col-md-3">
                                                <strong>Pag-IBIG:</strong> 
                                                <?php echo isset($edit_deductions_data['pag_ibig']['amount']) ? '₱' . number_format($edit_deductions_data['pag_ibig']['amount'], 2) : 'None'; ?>
                                            </div>
                                            <div class="col-md-3">
                                                <strong>PhilHealth:</strong> 
                                                <?php echo isset($edit_deductions_data['philhealth']['amount']) ? '₱' . number_format($edit_deductions_data['philhealth']['amount'], 2) : 'None'; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="alert alert-warning mt-3">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <small>Set amount to 0 to remove the deduction. All deduction changes are tracked with change amounts, percentages, and reasons - same as wage changes.</small>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <a href="employee_registration.php" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">Update Deductions</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <script>
        function showDeductionSection(type) {
            // Hide all sections
            document.getElementById('cash_advance_section').style.display = 'none';
            document.getElementById('sss_section').style.display = 'none';
            document.getElementById('pag_ibig_section').style.display = 'none';
            document.getElementById('philhealth_section').style.display = 'none';
            
            // Show selected section
            if (type) {
                document.getElementById(type + '_section').style.display = 'block';
            }
        }
        
        // Show section based on existing data on page load
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (isset($edit_deductions_data)): ?>
                <?php if (!empty($edit_deductions_data['cash_advance'])): ?>
                    showDeductionSection('cash_advance');
                    document.getElementById('deduction_type_selector').value = 'cash_advance';
                <?php elseif (!empty($edit_deductions_data['sss'])): ?>
                    showDeductionSection('sss');
                    document.getElementById('deduction_type_selector').value = 'sss';
                <?php elseif (!empty($edit_deductions_data['pag_ibig'])): ?>
                    showDeductionSection('pag_ibig');
                    document.getElementById('deduction_type_selector').value = 'pag_ibig';
                <?php elseif (!empty($edit_deductions_data['philhealth'])): ?>
                    showDeductionSection('philhealth');
                    document.getElementById('deduction_type_selector').value = 'philhealth';
                <?php endif; ?>
            <?php endif; ?>
        });
        </script>
        <?php endif; ?>

        <!-- View Wage History Modal -->
        <?php if (isset($view_wage_history_employee)): ?>
        <div class="modal fade show" id="viewWageHistoryModal" tabindex="-1" aria-labelledby="viewWageHistoryModalLabel" aria-hidden="false" style="display: block; background: rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="viewWageHistoryModalLabel">Wage History for 
                            <?php 
                                echo htmlspecialchars($view_wage_history_employee['lastname']) . ', ' . 
                                     htmlspecialchars($view_wage_history_employee['firstname']);
                                if (!empty($view_wage_history_employee['middlename'])) {
                                    echo ' ' . substr(htmlspecialchars($view_wage_history_employee['middlename']), 0, 1) . '.';
                                }
                                if (!empty($view_wage_history_employee['suffix'])) {
                                    echo ' ' . htmlspecialchars($view_wage_history_employee['suffix']);
                                }
                            ?>
                        </h5>
                        <a href="employee_registration.php" class="btn-close" aria-label="Close"></a>
                    </div>
                    <div class="modal-body">
                        <?php
                            try {
                                $historyStmt = $pdo->prepare("
                                    SELECT wh.*, u.firstname as changed_by_firstname, u.lastname as changed_by_lastname
                                    FROM wage_history wh
                                    JOIN users u ON wh.changed_by = u.id
                                    WHERE wh.employee_id = :employee_id
                                    ORDER BY wh.changed_at DESC
                                ");
                                $historyStmt->bindParam(':employee_id', $view_wage_history_employee['id']);
                                $historyStmt->execute();
                                $employee_wage_history = $historyStmt->fetchAll(PDO::FETCH_ASSOC);
                            } catch(PDOException $e) {
                                $employee_wage_history = [];
                                $employee_wage_error = "Error fetching wage history: " . $e->getMessage();
                            }
                        ?>
                        
                        <?php if (isset($employee_wage_error)): ?>
                            <div class="alert alert-danger"><?php echo $employee_wage_error; ?></div>
                        <?php elseif (empty($employee_wage_history)): ?>
                            <div class="alert alert-info">No wage history found for this employee.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped" id="employeeWageHistoryTable">
                                    <thead>
                                        <tr>
                                            <th>Date Changed</th>
                                            <th>Effectivity Date</th>
                                            <th>Old Wage</th>
                                            <th>New Wage</th>
                                            <th>Change Amount</th>
                                            <th>Type</th>
                                            <th>Changed By</th>
                                            <th>Reason</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($employee_wage_history as $history): ?>
                                            <tr>
                                                <td><?php echo date('M d, Y', strtotime($history['changed_at'])); ?></td>
                                                <td><?php echo !empty($history['effectivity_date']) ? date('M d, Y', strtotime($history['effectivity_date'])) : 'N/A'; ?></td>
                                                <td>₱<?php echo number_format($history['old_wage'], 2); ?></td>
                                                <td>₱<?php echo number_format($history['new_wage'], 2); ?></td>
                                                <td class="<?php echo $history['change_type'] == 'increase' ? 'text-success' : ($history['change_type'] == 'decrease' ? 'text-danger' : ''); ?>">
                                                    <?php echo $history['change_type'] == 'increase' ? '+' : ($history['change_type'] == 'decrease' ? '-' : ''); ?>
                                                    ₱<?php echo number_format(abs($history['change_amount']), 2); ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo $history['change_type'] == 'increase' ? 'success' : ($history['change_type'] == 'decrease' ? 'danger' : 'secondary'); ?>">
                                                        <?php echo ucfirst($history['change_type']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($history['changed_by_firstname'] . ' ' . $history['changed_by_lastname']); ?></td>
                                                <td><?php echo !empty($history['change_reason']) ? htmlspecialchars($history['change_reason']) : 'N/A'; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <a href="employee_registration.php" class="btn btn-secondary">Close</a>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- View Deduction History Modal -->
        <?php if (isset($view_deduction_history_employee)): ?>
        <div class="modal fade show" id="viewDeductionHistoryModal" tabindex="-1" aria-labelledby="viewDeductionHistoryModalLabel" aria-hidden="false" style="display: block; background: rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="viewDeductionHistoryModalLabel">Deduction History for 
                            <?php 
                                echo htmlspecialchars($view_deduction_history_employee['lastname']) . ', ' . 
                                    htmlspecialchars($view_deduction_history_employee['firstname']);
                                if (!empty($view_deduction_history_employee['middlename'])) {
                                    echo ' ' . substr(htmlspecialchars($view_deduction_history_employee['middlename']), 0, 1) . '.';
                                }
                                if (!empty($view_deduction_history_employee['suffix'])) {
                                    echo ' ' . htmlspecialchars($view_deduction_history_employee['suffix']);
                                }
                            ?>
                        </h5>
                        <a href="employee_registration.php" class="btn-close" aria-label="Close"></a>
                    </div>
                    <div class="modal-body">
                        <?php if (empty($view_deduction_history)): ?>
                            <div class="alert alert-info">No deduction history found for this employee.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped" id="viewDeductionHistoryTable">
                                    <thead>
                                        <tr>
                                            <th>Date Changed</th>
                                            <th>Deduction Type</th>
                                            <th>Old Amount</th>
                                            <th>New Amount</th>
                                            <th>Change Amount</th>
                                            <th>Type</th>
                                            <th>Details</th>
                                            <th>Changed By</th>
                                            <th>Reason</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($view_deduction_history as $history): ?>
                                            <tr>
                                                <td><?php echo date('M d, Y', strtotime($history['changed_at'])); ?></td>
                                                <td>
                                                    <?php
                                                        // Set badge color based on deduction type
                                                        $badge_color = 'secondary'; // default
                                                        $deduction_type_display = '';
                                                        
                                                        switch($history['deduction_type']) {
                                                            case 'cash_advance':
                                                                $badge_color = 'primary';
                                                                $deduction_type_display = 'CASH ADVANCE';
                                                                break;
                                                            case 'sss':
                                                                $badge_color = 'warning text-black';
                                                                $deduction_type_display = 'SSS';
                                                                break;
                                                            case 'pag_ibig':
                                                                $badge_color = 'success';
                                                                $deduction_type_display = 'PAG IBIG';
                                                                break;
                                                            case 'philhealth':
                                                                $badge_color = 'danger';
                                                                $deduction_type_display = 'PHILHEALTH';
                                                                break;
                                                            default:
                                                                $deduction_type_display = strtoupper(str_replace('_', ' ', $history['deduction_type']));
                                                        }
                                                    ?>
                                                    <span class="badge bg-<?php echo $badge_color; ?>">
                                                        <?php echo $deduction_type_display; ?>
                                                    </span>
                                                </td>
                                                <td>₱<?php echo number_format($history['old_amount'], 2); ?></td>
                                                <td>₱<?php echo number_format($history['new_amount'], 2); ?></td>
                                                <td class="<?php echo $history['change_type'] == 'increase' ? 'text-success' : ($history['change_type'] == 'decrease' ? 'text-danger' : ''); ?>">
                                                    <?php echo $history['change_type'] == 'increase' ? '+' : ($history['change_type'] == 'decrease' ? '-' : ''); ?>
                                                    ₱<?php echo number_format(abs($history['change_amount']), 2); ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo $history['change_type'] == 'increase' ? 'success' : ($history['change_type'] == 'decrease' ? 'danger' : 'secondary'); ?>">
                                                        <?php echo ucfirst($history['change_type']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($history['deduction_type'] == 'cash_advance'): ?>
                                                        <?php if (!empty($history['new_from_date']) || !empty($history['new_to_date'])): ?>
                                                            <small>
                                                                From: <?php echo !empty($history['new_from_date']) ? date('M d, Y', strtotime($history['new_from_date'])) : 'N/A'; ?><br>
                                                                To: <?php echo !empty($history['new_to_date']) ? date('M d, Y', strtotime($history['new_to_date'])) : 'N/A'; ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <?php if (!empty($history['new_effectivity_date'])): ?>
                                                            <small>Effectivity: <?php echo date('M d, Y', strtotime($history['new_effectivity_date'])); ?></small>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                    <?php if (!empty($history['new_notes'])): ?>
                                                        <br><small class="text-muted">Notes: <?php echo htmlspecialchars($history['new_notes']); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($history['changed_by_firstname'] . ' ' . $history['changed_by_lastname']); ?></td>
                                                <td><?php echo !empty($history['change_reason']) ? htmlspecialchars($history['change_reason']) : 'N/A'; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <a href="employee_registration.php" class="btn btn-secondary">Close</a>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>
        <script>
            // Simple sidebar toggle functionality
            document.getElementById('sidebarToggle').addEventListener('click', function() {
                document.body.classList.toggle('sb-sidenav-toggled');
            });
            
            // Function to show SweetAlert
            function showSweetAlert(icon, title, text) {
                Swal.fire({
                    icon: icon,
                    title: title,
                    text: text,
                    timer: 3000,
                    showConfirmButton: true
                });
            }
            
            // Function to confirm delete with SweetAlert
            function confirmDelete(event) {
                event.preventDefault();
                const form = event.target.closest('form');
                
                Swal.fire({
                    title: 'Are you sure?',
                    text: "You won't be able to revert this!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Yes, delete it!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        form.submit();
                    }
                });
            }
            
            document.addEventListener('DOMContentLoaded', function() {
                // Initialize DataTables
                const employeeTable = new simpleDatatables.DataTable("#employeeTable", {
                    searchable: true,
                    fixedHeight: false,
                    perPage: 10,
                    columns: [
                        { select: 0, sortable: true },
                        { select: 1, sortable: true },
                        { select: 2, sortable: true },
                        { select: 3, sortable: true },
                        { select: 4, sortable: false },
                        { select: 5, sortable: true },
                        { select: 6, sortable: false }
                    ]
                });
                
                // Show SweetAlert if there's a message
                <?php if (!empty($swal_data)): ?>
                    showSweetAlert('<?php echo $swal_data['icon']; ?>', '<?php echo $swal_data['title']; ?>', '<?php echo $swal_data['text']; ?>');
                <?php endif; ?>
                
                // If there was a form submission error, show the appropriate modal
                <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($swal_data) && $swal_data['icon'] === 'error'): ?>
                    <?php if (isset($_POST['edit_deductions'])): ?>
                        const deductionsModal = new bootstrap.Modal(document.getElementById('editDeductionsModal'));
                        deductionsModal.show();
                    <?php elseif (isset($_POST['edit_details'])): ?>
                        const editModal = new bootstrap.Modal(document.getElementById('editEmployeeModal'));
                        editModal.show();
                    <?php elseif (isset($_POST['edit_wage'])): ?>
                        const wageModal = new bootstrap.Modal(document.getElementById('editWageModal'));
                        wageModal.show();
                    <?php elseif (!isset($_POST['update_employee_details']) && !isset($_POST['update_employee_wage']) && !isset($_POST['update_employee_deductions']) && !isset($_POST['delete_employee'])): ?>
                        const addModal = new bootstrap.Modal(document.getElementById('addEmployeeModal'));
                        addModal.show();
                    <?php endif; ?>
                <?php endif; ?>
            });
        </script>
    </body>
</html>