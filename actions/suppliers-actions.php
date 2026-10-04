<?php
/**
 * actions/suppliers-actions.php
 *
 * Every action for suppliers.php lives in this one file: deleting, editing and
 * adding a supplier.
 *
 * The page pulls this file in at the top, so it runs in the page's scope. That
 * matters here: on a validation error the handler leaves $swal_data, the add_*
 * and the edit_* variables set, and the page's markup below reads them to reopen
 * the right modal with the submitted values still in the form. A redirect would
 * lose those, so the request re-renders exactly as it did when this code sat
 * inline in the page.
 */

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    return;
}
if (defined('OCP_SUPPLIERS_ACTIONS_RAN')) {
    return;
}
define('OCP_SUPPLIERS_ACTIONS_RAN', true);

/** Sets the SweetAlert payload the page renders. */
$ocp_swal = static function (string $title, string $text, string $icon): array {
    return ['title' => $title, 'text' => $text, 'icon' => $icon];
};

/** Keeps the submitted values so the add form can be repopulated. */
$ocp_keep_add = static function (array $v): void {
    $GLOBALS['add_supplier_name'] = $v['supplier_name'];
    $GLOBALS['add_contact_person'] = $v['contact_person'];
    $GLOBALS['add_phone'] = $v['phone'];
    $GLOBALS['add_email'] = $v['email'];
    $GLOBALS['add_address'] = $v['address'];
};

/** Keeps the submitted values so the edit form can be repopulated. */
$ocp_keep_edit = static function (array $v): void {
    $GLOBALS['edit_supplier_name'] = $v['supplier_name'];
    $GLOBALS['edit_contact_person'] = $v['contact_person'];
    $GLOBALS['edit_phone'] = $v['phone'];
    $GLOBALS['edit_email'] = $v['email'];
    $GLOBALS['edit_address'] = $v['address'];
};

// ------------------------------------------------------------------- delete
if (isset($_POST['delete_id'])) {
    try {
        $deleteStmt = $pdo->prepare("DELETE FROM suppliers WHERE id = :id");
        $deleteStmt->bindParam(':id', $_POST['delete_id']);

        $swal_data = $deleteStmt->execute()
            ? $ocp_swal('Success!', 'Supplier deleted successfully!', 'success')
            : $ocp_swal('Error!', 'Error deleting supplier. Please try again.', 'error');
    } catch (PDOException $e) {
        $swal_data = $ocp_swal('Database Error!', 'Database error: ' . $e->getMessage(), 'error');
    }
    return;
}

// --------------------------------------------------------------------- edit
if (isset($_POST['edit_id'])) {
    $edit_id = $_POST['edit_id'];
    $values = [
        'supplier_name' => trim($_POST['supplier_name'] ?? ''),
        'contact_person' => trim($_POST['contact_person'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'address' => trim($_POST['address'] ?? ''),
    ];
    $supplier_name = $values['supplier_name'];

    if ($supplier_name === '') {
        $swal_data = $ocp_swal('Validation Error!', 'Supplier name is required.', 'error');
        $ocp_keep_edit($values);
        return;
    }

    try {
        // The name must stay unique across the other suppliers
        $checkStmt = $pdo->prepare("SELECT id FROM suppliers WHERE supplier_name = :supplier_name AND id != :id");
        $checkStmt->bindParam(':supplier_name', $supplier_name);
        $checkStmt->bindParam(':id', $edit_id);
        $checkStmt->execute();

        if ($checkStmt->rowCount() > 0) {
            $swal_data = $ocp_swal('Error!', 'Supplier name already exists. Please use a different name.', 'error');
            $ocp_keep_edit($values);
            return;
        }

        $updateStmt = $pdo->prepare("UPDATE suppliers SET supplier_name = :supplier_name, contact_person = :contact_person, 
                                   phone = :phone, email = :email, address = :address WHERE id = :id");
        $updateStmt->bindParam(':id', $edit_id);
        $updateStmt->bindParam(':supplier_name', $values['supplier_name']);
        $updateStmt->bindParam(':contact_person', $values['contact_person']);
        $updateStmt->bindParam(':phone', $values['phone']);
        $updateStmt->bindParam(':email', $values['email']);
        $updateStmt->bindParam(':address', $values['address']);

        if ($updateStmt->execute()) {
            $swal_data = $ocp_swal('Success!', 'Supplier updated successfully!', 'success');
        } else {
            $swal_data = $ocp_swal('Error!', 'Error updating supplier. Please try again.', 'error');
            $ocp_keep_edit($values);
        }
    } catch (PDOException $e) {
        $swal_data = $ocp_swal('Database Error!', 'Database error: ' . $e->getMessage(), 'error');
        $ocp_keep_edit($values);
    }
    return;
}

// ---------------------------------------------------------------------- add
$values = [
    'supplier_name' => trim($_POST['supplier_name'] ?? ''),
    'contact_person' => trim($_POST['contact_person'] ?? ''),
    'phone' => trim($_POST['phone'] ?? ''),
    'email' => trim($_POST['email'] ?? ''),
    'address' => trim($_POST['address'] ?? ''),
];
$supplier_name = $values['supplier_name'];

// Keep the submitted values in case the add has to be retried
$ocp_keep_add($values);

if ($supplier_name === '') {
    $swal_data = $ocp_swal('Validation Error!', 'Supplier name is required.', 'error');
    return;
}

try {
    // The name must not already be taken
    $checkStmt = $pdo->prepare("SELECT id FROM suppliers WHERE supplier_name = :supplier_name");
    $checkStmt->bindParam(':supplier_name', $supplier_name);
    $checkStmt->execute();

    if ($checkStmt->rowCount() > 0) {
        $swal_data = $ocp_swal('Error!', 'Supplier already exists. Please use a different name.', 'error');
        return;
    }

    $insertStmt = $pdo->prepare("INSERT INTO suppliers (supplier_name, contact_person, phone, email, address) 
                               VALUES (:supplier_name, :contact_person, :phone, :email, :address)");
    $insertStmt->bindParam(':supplier_name', $values['supplier_name']);
    $insertStmt->bindParam(':contact_person', $values['contact_person']);
    $insertStmt->bindParam(':phone', $values['phone']);
    $insertStmt->bindParam(':email', $values['email']);
    $insertStmt->bindParam(':address', $values['address']);

    if ($insertStmt->execute()) {
        $swal_data = $ocp_swal('Success!', 'Supplier added successfully!', 'success');

        // The add form starts empty again
        $add_supplier_name = '';
        $add_contact_person = '';
        $add_phone = '';
        $add_email = '';
        $add_address = '';
    } else {
        $swal_data = $ocp_swal('Error!', 'Error adding supplier. Please try again.', 'error');
    }
} catch (PDOException $e) {
    $swal_data = $ocp_swal('Database Error!', 'Database error: ' . $e->getMessage(), 'error');
}
