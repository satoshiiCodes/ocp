<?php
/**
 * actions/gasoline_tank-actions.php
 *
 * Every action for gasoline_tank.php lives in this one file: deleting, editing and
 * adding a gasoline tank.
 *
 * The page pulls this file in at the top, so it runs in the page's scope. Each
 * branch ends by storing its alert and, where the form has to be retried, the
 * submitted values in the session, then redirecting back to the page - exactly as
 * it did inline.
 *
 * The handler bodies below are lifted verbatim from gasoline_tank.php: the
 * queries, the messages and the validation are unchanged.
 */

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    return;
}
if (defined('OCP_GASOLINE_TANK_ACTIONS_RAN')) {
    return;
}
define('OCP_GASOLINE_TANK_ACTIONS_RAN', true);
// ---------------------------------------------------------------- delete
if (isset($_POST['delete_tank'])) {
    $tank_id = $_POST['tank_id'];
    
    try {
        // Check if tank exists
        $checkStmt = $pdo->prepare("SELECT id FROM gasoline_tanks WHERE id = :id");
        $checkStmt->bindParam(':id', $tank_id);
        $checkStmt->execute();
        
        if ($checkStmt->rowCount() > 0) {
            // Delete tank
            $deleteStmt = $pdo->prepare("DELETE FROM gasoline_tanks WHERE id = :id");
            $deleteStmt->bindParam(':id', $tank_id);
            
            if ($deleteStmt->execute()) {
                $_SESSION['swal_data'] = [
                    'icon' => 'success',
                    'title' => 'Success!',
                    'text' => 'Gasoline tank deleted successfully!'
                ];
            } else {
                $_SESSION['swal_data'] = [
                    'icon' => 'error',
                    'title' => 'Error!',
                    'text' => 'Error deleting gasoline tank. Please try again.'
                ];
            }
        } else {
            $_SESSION['swal_data'] = [
                'icon' => 'error',
                'title' => 'Error!',
                'text' => 'Tank not found.'
            ];
        }
    } catch(PDOException $e) {
        $_SESSION['swal_data'] = [
            'icon' => 'error',
            'title' => 'Database Error!',
            'text' => 'Database error: ' . $e->getMessage()
        ];
    }
    header("Location: ".$_SERVER['PHP_SELF']);
    exit();
}

// ---------------------------------------------------------------- edit
if (isset($_POST['edit_tank'])) {
    $tank_id = $_POST['tank_id'];
    $tank_name = trim($_POST['tank_name']);
    $location = trim($_POST['location']);
    $capacity_liters = trim($_POST['capacity_liters']);
    $description = trim($_POST['description']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Basic validation
    if (empty($tank_name) || empty($location) || empty($capacity_liters)) {
        $_SESSION['swal_data'] = [
            'icon' => 'error',
            'title' => 'Validation Error!',
            'text' => 'Tank name, location, and capacity are required.'
        ];
        $_SESSION['form_data'] = $_POST;
        $_SESSION['form_type'] = 'edit';
    } else if (!is_numeric($capacity_liters) || $capacity_liters <= 0) {
        $_SESSION['swal_data'] = [
            'icon' => 'error',
            'title' => 'Validation Error!',
            'text' => 'Capacity must be a positive number.'
        ];
        $_SESSION['form_data'] = $_POST;
        $_SESSION['form_type'] = 'edit';
    } else {
        try {
            // Check if tank already exists (excluding current tank)
            $checkStmt = $pdo->prepare("SELECT id FROM gasoline_tanks WHERE tank_name = :tank_name AND location = :location AND id != :id");
            $checkStmt->bindParam(':tank_name', $tank_name);
            $checkStmt->bindParam(':location', $location);
            $checkStmt->bindParam(':id', $tank_id);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() > 0) {
                $_SESSION['swal_data'] = [
                    'icon' => 'error',
                    'title' => 'Error!',
                    'text' => 'Another tank with this name and location already exists.'
                ];
                $_SESSION['form_data'] = $_POST;
                $_SESSION['form_type'] = 'edit';
            } else {
                // Update tank
                $updateStmt = $pdo->prepare("UPDATE gasoline_tanks SET tank_name = :tank_name, location = :location, capacity_liters = :capacity_liters, description = :description, is_active = :is_active, updated_at = NOW() WHERE id = :id");
                $updateStmt->bindParam(':tank_name', $tank_name);
                $updateStmt->bindParam(':location', $location);
                $updateStmt->bindParam(':capacity_liters', $capacity_liters);
                $updateStmt->bindParam(':description', $description);
                $updateStmt->bindParam(':is_active', $is_active);
                $updateStmt->bindParam(':id', $tank_id);
                
                if ($updateStmt->execute()) {
                    $_SESSION['swal_data'] = [
                        'icon' => 'success',
                        'title' => 'Success!',
                        'text' => 'Gasoline tank updated successfully!'
                    ];
                } else {
                    $_SESSION['swal_data'] = [
                        'icon' => 'error',
                        'title' => 'Error!',
                        'text' => 'Error updating gasoline tank. Please try again.'
                    ];
                    $_SESSION['form_data'] = $_POST;
                    $_SESSION['form_type'] = 'edit';
                }
            }
        } catch(PDOException $e) {
            $_SESSION['swal_data'] = [
                'icon' => 'error',
                'title' => 'Database Error!',
                'text' => 'Database error: ' . $e->getMessage()
            ];
            $_SESSION['form_data'] = $_POST;
            $_SESSION['form_type'] = 'edit';
        }
    }
    header("Location: ".$_SERVER['PHP_SELF']);
    exit();
}

// ---------------------------------------------------------------- add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tank_name']) && !isset($_POST['edit_tank'])) {
    $tank_name = trim($_POST['tank_name']);
    $location = trim($_POST['location']);
    $capacity_liters = trim($_POST['capacity_liters']);
    $description = trim($_POST['description']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Basic validation
    if (empty($tank_name) || empty($location) || empty($capacity_liters)) {
        $_SESSION['swal_data'] = [
            'icon' => 'error',
            'title' => 'Validation Error!',
            'text' => 'Tank name, location, and capacity are required.'
        ];
        $_SESSION['form_data'] = $_POST;
        $_SESSION['form_type'] = 'add';
    } else if (!is_numeric($capacity_liters) || $capacity_liters <= 0) {
        $_SESSION['swal_data'] = [
            'icon' => 'error',
            'title' => 'Validation Error!',
            'text' => 'Capacity must be a positive number.'
        ];
        $_SESSION['form_data'] = $_POST;
        $_SESSION['form_type'] = 'add';
    } else {
        try {
            // Check if tank already exists
            $checkStmt = $pdo->prepare("SELECT id FROM gasoline_tanks WHERE tank_name = :tank_name AND location = :location");
            $checkStmt->bindParam(':tank_name', $tank_name);
            $checkStmt->bindParam(':location', $location);
            $checkStmt->execute();

            if ($checkStmt->rowCount() > 0) {
                $_SESSION['swal_data'] = [
                    'icon' => 'error',
                    'title' => 'Error!',
                    'text' => 'A tank with this name and location already exists.'
                ];
                $_SESSION['form_data'] = $_POST;
                $_SESSION['form_type'] = 'add';
            } else {
                // Insert new tank
                $insertStmt = $pdo->prepare("INSERT INTO gasoline_tanks (tank_name, location, capacity_liters, description, is_active) 
                                        VALUES (:tank_name, :location, :capacity_liters, :description, :is_active)");
                $insertStmt->bindParam(':tank_name', $tank_name);
                $insertStmt->bindParam(':location', $location);
                $insertStmt->bindParam(':capacity_liters', $capacity_liters);
                $insertStmt->bindParam(':description', $description);
                $insertStmt->bindParam(':is_active', $is_active);
                
                if ($insertStmt->execute()) {
                    $_SESSION['swal_data'] = [
                        'icon' => 'success',
                        'title' => 'Success!',
                        'text' => 'Gasoline tank added successfully!'
                    ];
                } else {
                    $_SESSION['swal_data'] = [
                        'icon' => 'error',
                        'title' => 'Error!',
                        'text' => 'Error adding gasoline tank. Please try again.'
                    ];
                    $_SESSION['form_data'] = $_POST;
                    $_SESSION['form_type'] = 'add';
                }
            }

        } catch(PDOException $e) {
            $_SESSION['swal_data'] = [
                'icon' => 'error',
                'title' => 'Database Error!',
                'text' => 'Database error: ' . $e->getMessage()
            ];
            $_SESSION['form_data'] = $_POST;
            $_SESSION['form_type'] = 'add';
        }
    }
    header("Location: ".$_SERVER['PHP_SELF']);
    exit();
}

