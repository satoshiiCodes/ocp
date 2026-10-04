<?php
/**
 * actions/warehouses-actions.php
 *
 * Every action for warehouses.php lives in this one file: deleting, updating and
 * adding a warehouse.
 *
 * The page pulls this file in at the top, so it runs in the page's scope. On a
 * rejected submission it leaves $swal_data set and the page's markup below reads
 * it to show the message and reopen the right form. On success it redirects, so a
 * refresh cannot resubmit.
 *
 * The handler bodies below are lifted verbatim from warehouses.php: the queries,
 * the messages and the validation are unchanged.
 */

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    return;
}
if (defined('OCP_WAREHOUSES_ACTIONS_RAN')) {
    return;
}
define('OCP_WAREHOUSES_ACTIONS_RAN', true);

$ocp_is_delete = isset($_POST['delete_id']);
$ocp_is_update = isset($_POST['update_id']);
// ---------------------------------------------------------------- delete
if (isset($_POST['delete_id'])) {
    try {
        $deleteStmt = $pdo->prepare("DELETE FROM warehouses WHERE id = :id");
        $deleteStmt->bindParam(':id', $_POST['delete_id']);
        
        if ($deleteStmt->execute()) {
            $swal_data = [
                'title' => 'Success!',
                'text' => 'Warehouse deleted successfully!',
                'icon' => 'success'
            ];
        } else {
            $swal_data = [
                'title' => 'Error!',
                'text' => 'Error deleting warehouse. Please try again.',
                'icon' => 'error'
            ];
        }
    } catch(PDOException $e) {
        $swal_data = [
            'title' => 'Database Error!',
            'text' => 'Database error: ' . $e->getMessage(),
            'icon' => 'error'
        ];
    }
}

// ---------------------------------------------------------------- update
if (isset($_POST['update_id'])) {
    $warehouse_name = trim($_POST['warehouse_name']);
    $location = trim($_POST['location']);
    $capacity = trim($_POST['capacity']);
    $manager = trim($_POST['manager']);
    $phone = trim($_POST['phone']);
    $update_id = $_POST['update_id'];
    
    // Basic validation
    if (empty($warehouse_name) || empty($location)) {
        $swal_data = [
            'title' => 'Validation Error!',
            'text' => 'Warehouse name and location are required.',
            'icon' => 'error'
        ];
    } else {
        try {
            // Check if warehouse already exists (excluding current record)
            $checkStmt = $pdo->prepare("SELECT id FROM warehouses WHERE warehouse_name = :warehouse_name AND location = :location AND id != :id");
            $checkStmt->bindParam(':warehouse_name', $warehouse_name);
            $checkStmt->bindParam(':location', $location);
            $checkStmt->bindParam(':id', $update_id);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() > 0) {
                $swal_data = [
                    'title' => 'Error!',
                    'text' => 'Another warehouse with this name and location already exists.',
                    'icon' => 'error'
                ];
            } else {
                // Update warehouse
                $updateStmt = $pdo->prepare("UPDATE warehouses SET warehouse_name = :warehouse_name, location = :location, 
                                           capacity = :capacity, manager = :manager, phone = :phone WHERE id = :id");
                $updateStmt->bindParam(':warehouse_name', $warehouse_name);
                $updateStmt->bindParam(':location', $location);
                $updateStmt->bindParam(':capacity', $capacity);
                $updateStmt->bindParam(':manager', $manager);
                $updateStmt->bindParam(':phone', $phone);
                $updateStmt->bindParam(':id', $update_id);
                
                if ($updateStmt->execute()) {
                    $swal_data = [
                        'title' => 'Success!',
                        'text' => 'Warehouse updated successfully!',
                        'icon' => 'success'
                    ];
                } else {
                    $swal_data = [
                        'title' => 'Error!',
                        'text' => 'Error updating warehouse. Please try again.',
                        'icon' => 'error'
                    ];
                }
            }
        } catch(PDOException $e) {
            $swal_data = [
                'title' => 'Database Error!',
                'text' => 'Database error: ' . $e->getMessage(),
                'icon' => 'error'
            ];
        }
    }
}

// ---------------------------------------------------------------- add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['delete_id']) && !isset($_POST['update_id'])) {
    $warehouse_name = trim($_POST['warehouse_name']);
    $location = trim($_POST['location']);
    $capacity = trim($_POST['capacity']);
    $manager = trim($_POST['manager']);
    $phone = trim($_POST['phone']);
    
    // Basic validation
    if (empty($warehouse_name) || empty($location)) {
        $swal_data = [
            'title' => 'Validation Error!',
            'text' => 'Warehouse name and location are required.',
            'icon' => 'error'
        ];
    } else {
        try {
            // Check if warehouse already exists
            $checkStmt = $pdo->prepare("SELECT id FROM warehouses WHERE warehouse_name = :warehouse_name AND location = :location");
            $checkStmt->bindParam(':warehouse_name', $warehouse_name);
            $checkStmt->bindParam(':location', $location);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() > 0) {
                $swal_data = [
                    'title' => 'Error!',
                    'text' => 'Warehouse with this name and location already exists.',
                    'icon' => 'error'
                ];
            } else {
                // Insert new warehouse
                $insertStmt = $pdo->prepare("INSERT INTO warehouses (warehouse_name, location, capacity, manager, phone) 
                                           VALUES (:warehouse_name, :location, :capacity, :manager, :phone)");
                $insertStmt->bindParam(':warehouse_name', $warehouse_name);
                $insertStmt->bindParam(':location', $location);
                $insertStmt->bindParam(':capacity', $capacity);
                $insertStmt->bindParam(':manager', $manager);
                $insertStmt->bindParam(':phone', $phone);
                
                if ($insertStmt->execute()) {
                    // Store it and redirect, rather than setting $swal_data and redirecting:
                    // the redirect is answered by a fresh GET where this file does not run,
                    // so a message left only in $swal_data is lost and nothing is shown.
                    $_SESSION['swal_data'] = [
                        'title' => 'Success!',
                        'text' => 'Warehouse added successfully!',
                        'icon' => 'success'
                    ];

                    // Clear form fields by redirecting
                    header("Location: ".$_SERVER['PHP_SELF']);
                    exit();
                } else {
                    $swal_data = [
                        'title' => 'Error!',
                        'text' => 'Error adding warehouse. Please try again.',
                        'icon' => 'error'
                    ];
                }
            }
        } catch(PDOException $e) {
            $swal_data = [
                'title' => 'Database Error!',
                'text' => 'Database error: ' . $e->getMessage(),
                'icon' => 'error'
            ];
        }
    }
}

