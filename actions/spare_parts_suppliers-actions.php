<?php
/**
 * actions/spare_parts_suppliers-actions.php
 *
 * Every action for spare_parts_suppliers lives in this one file.
 *
 * The page pulls this file in at the top, so it runs in the page's scope: the
 * database handle, the session and $_POST behave exactly as they did when this
 * code sat inline. A rejected submission leaves its message set and the markup
 * below repopulates the form from $_POST; a success redirects.
 *
 * The block below is lifted verbatim from spare_parts_suppliers: the queries, the messages and
 * the validation are unchanged.
 */

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    return;
}
if (defined('OCP_SPARE_PARTS_SUPPLIERS_ACTIONS_RAN')) {
    return;
}
define('OCP_SPARE_PARTS_SUPPLIERS_ACTIONS_RAN', true);

// The message variables the page's markup reads. Both are filled in below.
$message = '';
$message_type = '';

// Process form submission
$message = '';
$message_type = ''; // success or danger
$swal_data = []; // For SweetAlert2 data

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if it's a delete operation
    if (isset($_POST['delete_id'])) {
        $delete_id = $_POST['delete_id'];
        
        try {
            $deleteStmt = $pdo->prepare("DELETE FROM spare_parts_suppliers WHERE id = :id");
            $deleteStmt->bindParam(':id', $delete_id);
            
            if ($deleteStmt->execute()) {
                $swal_data = [
                    'title' => 'Success!',
                    'text' => 'Supplier deleted successfully!',
                    'icon' => 'success'
                ];
            } else {
                $swal_data = [
                    'title' => 'Error!',
                    'text' => 'Error deleting supplier. Please try again.',
                    'icon' => 'error'
                ];
            }
        } catch(PDOException $e) {
            $swal_data = [
                'title' => 'Database Error!',
                'text' => 'Error: ' . $e->getMessage(),
                'icon' => 'error'
            ];
        }
    } 
    // Check if it's an edit operation
    else if (isset($_POST['edit_id'])) {
        $edit_id = $_POST['edit_id'];
        $supplier_name = trim($_POST['supplier_name']);
        $contact_person = trim($_POST['contact_person']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $address = trim($_POST['address']);
        
        // Basic validation
        if (empty($supplier_name)) {
            $swal_data = [
                'title' => 'Validation Error!',
                'text' => 'Supplier name is required.',
                'icon' => 'error'
            ];
        } else {
            try {
                // Check if supplier name already exists (excluding current supplier)
                $checkStmt = $pdo->prepare("SELECT id FROM spare_parts_suppliers WHERE supplier_name = :supplier_name AND id != :id");
                $checkStmt->bindParam(':supplier_name', $supplier_name);
                $checkStmt->bindParam(':id', $edit_id);
                $checkStmt->execute();
                
                if ($checkStmt->rowCount() > 0) {
                    $swal_data = [
                        'title' => 'Error!',
                        'text' => 'Supplier name already exists. Please use a different name.',
                        'icon' => 'error'
                    ];
                } else {
                    // Update supplier
                    $updateStmt = $pdo->prepare("UPDATE spare_parts_suppliers SET supplier_name = :supplier_name, contact_person = :contact_person, email = :email, phone = :phone, address = :address WHERE id = :id");
                    $updateStmt->bindParam(':supplier_name', $supplier_name);
                    $updateStmt->bindParam(':contact_person', $contact_person);
                    $updateStmt->bindParam(':email', $email);
                    $updateStmt->bindParam(':phone', $phone);
                    $updateStmt->bindParam(':address', $address);
                    $updateStmt->bindParam(':id', $edit_id);
                    
                    if ($updateStmt->execute()) {
                        $swal_data = [
                            'title' => 'Success!',
                            'text' => 'Supplier updated successfully!',
                            'icon' => 'success'
                        ];
                    } else {
                        $swal_data = [
                            'title' => 'Error!',
                            'text' => 'Error updating supplier. Please try again.',
                            'icon' => 'error'
                        ];
                    }
                }
            } catch(PDOException $e) {
                $swal_data = [
                    'title' => 'Database Error!',
                    'text' => 'Error: ' . $e->getMessage(),
                    'icon' => 'error'
                ];
            }
        }
    }
    // It's an add operation
    else {
        $supplier_name = trim($_POST['supplier_name']);
        $contact_person = trim($_POST['contact_person']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $address = trim($_POST['address']);
        
        // Basic validation
        if (empty($supplier_name)) {
            $swal_data = [
                'title' => 'Validation Error!',
                'text' => 'Supplier name is required.',
                'icon' => 'error'
            ];
        } else {
            try {
                // Check if supplier name already exists
                $checkStmt = $pdo->prepare("SELECT id FROM spare_parts_suppliers WHERE supplier_name = :supplier_name");
                $checkStmt->bindParam(':supplier_name', $supplier_name);
                $checkStmt->execute();
                
                if ($checkStmt->rowCount() > 0) {
                    $swal_data = [
                        'title' => 'Error!',
                        'text' => 'Supplier name already exists. Please use a different name.',
                        'icon' => 'error'
                    ];
                } else {
                    // Insert new supplier
                    $insertStmt = $pdo->prepare("INSERT INTO spare_parts_suppliers (supplier_name, contact_person, email, phone, address) VALUES (:supplier_name, :contact_person, :email, :phone, :address)");
                    $insertStmt->bindParam(':supplier_name', $supplier_name);
                    $insertStmt->bindParam(':contact_person', $contact_person);
                    $insertStmt->bindParam(':email', $email);
                    $insertStmt->bindParam(':phone', $phone);
                    $insertStmt->bindParam(':address', $address);
                    
                    if ($insertStmt->execute()) {
                        $swal_data = [
                            'title' => 'Success!',
                            'text' => 'Supplier added successfully!',
                            'icon' => 'success'
                        ];
                    } else {
                        $swal_data = [
                            'title' => 'Error!',
                            'text' => 'Error adding supplier. Please try again.',
                            'icon' => 'error'
                        ];
                    }
                }
            } catch(PDOException $e) {
                $swal_data = [
                    'title' => 'Database Error!',
                    'text' => 'Error: ' . $e->getMessage(),
                    'icon' => 'error'
                ];
            }
        }
    }
}
