<?php
/**
 * actions/gasoline_suppliers-actions.php
 *
 * Every action for gasoline_suppliers.php lives in this one file: deleting,
 * updating and adding a gasoline supplier.
 *
 * The page pulls this file in at the top, so it runs in the page's scope. A
 * rejected submission leaves the $swal_* message and the form values set, and the
 * markup below shows the message and reopens the right form; a success redirects.
 *
 * The handler bodies below are lifted verbatim from gasoline_suppliers.php: the
 * queries, the messages and the validation are unchanged.
 */

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    return;
}
if (defined('OCP_GASOLINE_SUPPLIERS_ACTIONS_RAN')) {
    return;
}
define('OCP_GASOLINE_SUPPLIERS_ACTIONS_RAN', true);
// ---------------------------------------------------------------- delete
if (isset($_POST['delete_supplier'])) {
    $supplier_id = $_POST['supplier_id'];
    
    try {
        // Check if supplier exists
        $checkStmt = $pdo->prepare("SELECT id FROM gasoline_suppliers WHERE id = :id");
        $checkStmt->bindParam(':id', $supplier_id);
        $checkStmt->execute();
        
        if ($checkStmt->rowCount() > 0) {
            // Delete supplier
            $deleteStmt = $pdo->prepare("DELETE FROM gasoline_suppliers WHERE id = :id");
            $deleteStmt->bindParam(':id', $supplier_id);
            
            if ($deleteStmt->execute()) {
                $swal_title = 'Success!';
                $swal_message = 'Gasoline supplier deleted successfully!';
                $swal_type = 'success';
                
            } else {
                $swal_title = 'Error!';
                $swal_message = 'Error deleting gasoline supplier. Please try again.';
                $swal_type = 'error';
            }
        } else {
            $swal_title = 'Error!';
            $swal_message = 'Gasoline supplier not found.';
            $swal_type = 'error';
        }
    } catch(PDOException $e) {
        $swal_title = 'Database Error!';
        $swal_message = 'Database error: ' . $e->getMessage();
        $swal_type = 'error';
    }
}

// ---------------------------------------------------------------- update
if (isset($_POST['update_supplier'])) {
    $supplier_id = $_POST['supplier_id'];
    $supplier_name = trim($_POST['supplier_name']);
    $contact_person = trim($_POST['contact_person']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $address = trim($_POST['address']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Basic validation
    if (empty($supplier_name)) {
        $swal_title = 'Validation Error!';
        $swal_message = 'Supplier name is required.';
        $swal_type = 'error';
    } else {
        try {
            // Check if supplier already exists (excluding current supplier)
            $checkStmt = $pdo->prepare("SELECT id FROM gasoline_suppliers WHERE supplier_name = :supplier_name AND id != :id");
            $checkStmt->bindParam(':supplier_name', $supplier_name);
            $checkStmt->bindParam(':id', $supplier_id);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() > 0) {
                $swal_title = 'Error!';
                $swal_message = 'Gasoline supplier already exists. Please use a different name.';
                $swal_type = 'error';
            } else {
                // Update supplier
                $updateStmt = $pdo->prepare("UPDATE gasoline_suppliers SET supplier_name = :supplier_name, contact_person = :contact_person, phone = :phone, email = :email, address = :address, is_active = :is_active, updated_at = NOW() WHERE id = :id");
                $updateStmt->bindParam(':id', $supplier_id);
                $updateStmt->bindParam(':supplier_name', $supplier_name);
                $updateStmt->bindParam(':contact_person', $contact_person);
                $updateStmt->bindParam(':phone', $phone);
                $updateStmt->bindParam(':email', $email);
                $updateStmt->bindParam(':address', $address);
                $updateStmt->bindParam(':is_active', $is_active);
                
                if ($updateStmt->execute()) {
                    $swal_title = 'Success!';
                    $swal_message = 'Gasoline supplier updated successfully!';
                    $swal_type = 'success';
                    
                } else {
                    $swal_title = 'Error!';
                    $swal_message = 'Error updating gasoline supplier. Please try again.';
                    $swal_type = 'error';
                }
            }
        } catch(PDOException $e) {
            $swal_title = 'Database Error!';
            $swal_message = 'Database error: ' . $e->getMessage();
            $swal_type = 'error';
        }
    }
}

// ---------------------------------------------------------------- add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['supplier_name']) && !isset($_POST['update_supplier']) && !isset($_POST['delete_supplier'])) {
    $supplier_name = trim($_POST['supplier_name']);
    $supplier_type = 'Fuel'; // Fixed value as requested
    $contact_person = trim($_POST['contact_person']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $address = trim($_POST['address']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Basic validation
    if (empty($supplier_name)) {
        $swal_title = 'Validation Error!';
        $swal_message = 'Supplier name is required.';
        $swal_type = 'error';
    } else {
        try {
            // Check if supplier already exists
            $checkStmt = $pdo->prepare("SELECT id FROM gasoline_suppliers WHERE supplier_name = :supplier_name");
            $checkStmt->bindParam(':supplier_name', $supplier_name);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() > 0) {
                $swal_title = 'Error!';
                $swal_message = 'Gasoline supplier already exists. Please use a different name.';
                $swal_type = 'error';
            } else {
                // Insert new supplier
                $insertStmt = $pdo->prepare("INSERT INTO gasoline_suppliers (supplier_name, supplier_type, contact_person, phone, email, address, is_active) 
                                           VALUES (:supplier_name, :supplier_type, :contact_person, :phone, :email, :address, :is_active)");
                $insertStmt->bindParam(':supplier_name', $supplier_name);
                $insertStmt->bindParam(':supplier_type', $supplier_type);
                $insertStmt->bindParam(':contact_person', $contact_person);
                $insertStmt->bindParam(':phone', $phone);
                $insertStmt->bindParam(':email', $email);
                $insertStmt->bindParam(':address', $address);
                $insertStmt->bindParam(':is_active', $is_active);
                
                if ($insertStmt->execute()) {
                    $swal_title = 'Success!';
                    $swal_message = 'Gasoline supplier added successfully!';
                    $swal_type = 'success';
                    
                } else {
                    $swal_title = 'Error!';
                    $swal_message = 'Error adding gasoline supplier. Please try again.';
                    $swal_type = 'error';
                }
            }
        } catch(PDOException $e) {
            $swal_title = 'Database Error!';
            $swal_message = 'Database error: ' . $e->getMessage();
            $swal_type = 'error';
        }
    }
}

