<?php
/**
 * actions/vehicles-actions.php
 *
 * Every action for vehicles.php lives in this one file: deleting a vehicle, and
 * adding or editing one.
 *
 * The page pulls this file in at the top, so it runs in the page's scope. The
 * delete arrives as a GET (?delete_id=), the add and edit as POSTs. On a
 * validation error the handler leaves $show_modal set and the page reopens the
 * add or edit modal with the message from the session; on success it redirects so
 * a refresh cannot resubmit.
 */

$ocp_is_post = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
$ocp_is_delete = isset($_GET['delete_id']);

if (!$ocp_is_post && !$ocp_is_delete) {
    return;
}
if (defined('OCP_VEHICLES_ACTIONS_RAN')) {
    return;
}
define('OCP_VEHICLES_ACTIONS_RAN', true);

/** Stores the alert the page renders and returns to the page. */
$ocp_alert = static function (string $type, string $title, string $text): void {
    $_SESSION['alert'] = ['type' => $type, 'title' => $title, 'text' => $text];
};

// -------------------------------------------------------------- delete (GET)
if ($ocp_is_delete) {
    try {
        $deleteStmt = $pdo->prepare("DELETE FROM vehicles WHERE id = :id");
        $deleteStmt->bindParam(':id', $_GET['delete_id']);

        if ($deleteStmt->execute()) {
            $ocp_alert('success', 'Success!', 'Vehicle deleted successfully!');
        } else {
            $ocp_alert('error', 'Error!', 'Error deleting vehicle. Please try again.');
        }
    } catch (PDOException $e) {
        $ocp_alert('error', 'Database Error!', 'Database error: ' . $e->getMessage());
    }
    header('Location: vehicles.php');
    exit();
}

// --------------------------------------------------------------------- edit
if (isset($_POST['action']) && $_POST['action'] === 'edit_vehicle') {
    $vehicle_id = $_POST['vehicle_id'] ?? 0;
    $vehicle_name = trim($_POST['vehicle_name'] ?? '');
    $plate_number = trim($_POST['plate_number'] ?? '');
    $fuel_type = trim($_POST['fuel_type'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if ($vehicle_name === '' || $plate_number === '') {
        $ocp_alert('error', 'Validation Error!', 'Vehicle name and plate number are required.');
        $show_modal = 'edit';
        return;
    }

    try {
        // No other vehicle may hold this plate number
        $checkStmt = $pdo->prepare("SELECT id FROM vehicles WHERE plate_number = :plate_number AND id != :id");
        $checkStmt->bindParam(':plate_number', $plate_number);
        $checkStmt->bindParam(':id', $vehicle_id);
        $checkStmt->execute();

        if ($checkStmt->rowCount() > 0) {
            $ocp_alert('error', 'Duplicate Entry!', 'Another vehicle with this plate number already exists.');
            $show_modal = 'edit';
            return;
        }

        $updateStmt = $pdo->prepare("UPDATE vehicles SET vehicle_name = :vehicle_name, plate_number = :plate_number, 
                                   fuel_type = :fuel_type, description = :description, is_active = :is_active 
                                   WHERE id = :id");
        $updateStmt->bindParam(':vehicle_name', $vehicle_name);
        $updateStmt->bindParam(':plate_number', $plate_number);
        $updateStmt->bindParam(':fuel_type', $fuel_type);
        $updateStmt->bindParam(':description', $description);
        $updateStmt->bindParam(':is_active', $is_active);
        $updateStmt->bindParam(':id', $vehicle_id);

        if ($updateStmt->execute()) {
            $ocp_alert('success', 'Success!', 'Vehicle updated successfully!');
            header('Location: vehicles.php');
            exit();
        }

        $ocp_alert('error', 'Error!', 'Error updating vehicle. Please try again.');
        $show_modal = 'edit';
    } catch (PDOException $e) {
        $ocp_alert('error', 'Database Error!', 'Database error: ' . $e->getMessage());
        $show_modal = 'edit';
    }
    return;
}

// ---------------------------------------------------------------------- add
$vehicle_name = trim($_POST['vehicle_name'] ?? '');
$plate_number = trim($_POST['plate_number'] ?? '');
$fuel_type = trim($_POST['fuel_type'] ?? '');
$description = trim($_POST['description'] ?? '');
$is_active = isset($_POST['is_active']) ? 1 : 0;

if ($vehicle_name === '' || $plate_number === '') {
    $ocp_alert('error', 'Validation Error!', 'Vehicle name and plate number are required.');
    $show_modal = 'add';
    return;
}

try {
    // The plate number must not already be taken
    $checkStmt = $pdo->prepare("SELECT id FROM vehicles WHERE plate_number = :plate_number");
    $checkStmt->bindParam(':plate_number', $plate_number);
    $checkStmt->execute();

    if ($checkStmt->rowCount() > 0) {
        $ocp_alert('error', 'Duplicate Entry!', 'Vehicle with this plate number already exists.');
        $show_modal = 'add';
        return;
    }

    $insertStmt = $pdo->prepare("INSERT INTO vehicles (vehicle_name, plate_number, fuel_type, description, is_active) 
                               VALUES (:vehicle_name, :plate_number, :fuel_type, :description, :is_active)");
    $insertStmt->bindParam(':vehicle_name', $vehicle_name);
    $insertStmt->bindParam(':plate_number', $plate_number);
    $insertStmt->bindParam(':fuel_type', $fuel_type);
    $insertStmt->bindParam(':description', $description);
    $insertStmt->bindParam(':is_active', $is_active);

    if ($insertStmt->execute()) {
        $ocp_alert('success', 'Success!', 'Vehicle added successfully!');
        $_POST = array();

        header('Location: vehicles.php');
        exit();
    }

    $ocp_alert('error', 'Error!', 'Error adding vehicle. Please try again.');
    $show_modal = 'add';
} catch (PDOException $e) {
    $ocp_alert('error', 'Database Error!', 'Database error: ' . $e->getMessage());
    $show_modal = 'add';
}
