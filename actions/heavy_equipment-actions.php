<?php
/**
 * actions/heavy_equipment-actions.php
 *
 * Every action for heavy_equipment.php lives in this one file: deleting, editing
 * and adding a piece of equipment.
 *
 * The page pulls this file in at the top, so it runs in the page's scope. All
 * three branches report through $_SESSION['alert'] and redirect straight back to
 * the page, exactly as they did inline.
 */

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    return;
}
if (defined('OCP_HEAVY_EQUIPMENT_ACTIONS_RAN')) {
    return;
}
define('OCP_HEAVY_EQUIPMENT_ACTIONS_RAN', true);

/** Stores the alert the page renders, and returns to the page. */
$ocp_redirect_alert = static function (string $type, string $title, string $message): void {
    $_SESSION['alert'] = ['type' => $type, 'title' => $title, 'message' => $message];
    header('Location: heavy_equipment.php');
    exit();
};

/** The database-failure branch every action shares. */
$ocp_db_fail = static function (PDOException $e) use ($ocp_redirect_alert): void {
    $ocp_redirect_alert('error', 'Database Error', 'Database error: ' . $e->getMessage());
};

/** The general-failure branch every action shares. */
$ocp_fail = static function (Exception $e) use ($ocp_redirect_alert): void {
    $ocp_redirect_alert('error', 'Error', 'Error: ' . $e->getMessage());
};

// ------------------------------------------------------------------- delete
if (isset($_POST['delete_id'])) {
    try {
        if (!is_numeric($_POST['delete_id'])) {
            throw new Exception('Invalid equipment ID');
        }

        $deleteStmt = $pdo->prepare("DELETE FROM equipment WHERE id = :id");
        $deleteStmt->bindParam(':id', $_POST['delete_id'], PDO::PARAM_INT);

        if (!$deleteStmt->execute()) {
            throw new Exception('Error deleting equipment');
        }

        $ocp_redirect_alert('success', 'Success!', 'Equipment deleted successfully!');
    } catch (PDOException $e) {
        $ocp_db_fail($e);
    } catch (Exception $e) {
        $ocp_fail($e);
    }
}

// --------------------------------------------------------------------- edit
if (isset($_POST['edit_id'])) {
    try {
        if (!is_numeric($_POST['edit_id'])) {
            throw new Exception('Invalid equipment ID');
        }

        $equipment_name = trim($_POST['equipment_name'] ?? '');
        $fuel_type = trim($_POST['fuel_type'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if ($equipment_name === '') {
            throw new Exception('Equipment name is required');
        }

        // No other piece of equipment may carry this name
        $checkStmt = $pdo->prepare("SELECT id FROM equipment WHERE equipment_name = :equipment_name AND id != :id");
        $checkStmt->bindParam(':equipment_name', $equipment_name);
        $checkStmt->bindParam(':id', $_POST['edit_id'], PDO::PARAM_INT);
        $checkStmt->execute();

        if ($checkStmt->rowCount() > 0) {
            throw new Exception('Equipment with this name already exists');
        }

        $updateStmt = $pdo->prepare("UPDATE equipment SET equipment_name = :equipment_name, fuel_type = :fuel_type, 
                                    description = :description, is_active = :is_active WHERE id = :id");
        $updateStmt->bindParam(':id', $_POST['edit_id'], PDO::PARAM_INT);
        $updateStmt->bindParam(':equipment_name', $equipment_name);
        $updateStmt->bindParam(':fuel_type', $fuel_type);
        $updateStmt->bindParam(':description', $description);
        $updateStmt->bindParam(':is_active', $is_active, PDO::PARAM_INT);

        if (!$updateStmt->execute()) {
            throw new Exception('Error updating equipment');
        }

        $ocp_redirect_alert('success', 'Success!', 'Equipment updated successfully!');
    } catch (PDOException $e) {
        $ocp_db_fail($e);
    } catch (Exception $e) {
        $ocp_fail($e);
    }
}

// ---------------------------------------------------------------------- add
if (isset($_POST['equipment_name']) && !isset($_POST['edit_id']) && !isset($_POST['delete_id'])) {
    $equipment_name = trim($_POST['equipment_name'] ?? '');
    $fuel_type = trim($_POST['fuel_type'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if ($equipment_name === '') {
        $ocp_redirect_alert('error', 'Validation Error', 'Equipment name is required.');
    }

    try {
        // The name must not already be taken
        $checkStmt = $pdo->prepare("SELECT id FROM equipment WHERE equipment_name = :equipment_name");
        $checkStmt->bindParam(':equipment_name', $equipment_name);
        $checkStmt->execute();

        if ($checkStmt->rowCount() > 0) {
            $ocp_redirect_alert('error', 'Validation Error', 'Equipment with this name already exists.');
        }

        $insertStmt = $pdo->prepare("INSERT INTO equipment (equipment_name, fuel_type, description, is_active) 
                                   VALUES (:equipment_name, :fuel_type, :description, :is_active)");
        $insertStmt->bindParam(':equipment_name', $equipment_name);
        $insertStmt->bindParam(':fuel_type', $fuel_type);
        $insertStmt->bindParam(':description', $description);
        $insertStmt->bindParam(':is_active', $is_active, PDO::PARAM_INT);

        if (!$insertStmt->execute()) {
            throw new Exception('Error adding equipment');
        }

        $ocp_redirect_alert('success', 'Success!', 'Equipment added successfully!');
    } catch (PDOException $e) {
        $ocp_db_fail($e);
    } catch (Exception $e) {
        $ocp_fail($e);
    }
}
