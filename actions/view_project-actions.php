<?php
/**
 * actions/view_project-actions.php
 *
 * Every action for view_project.php lives in this one file: adding workers to the
 * project, adding a vehicle or equipment rental, and removing either.
 *
 * The page pulls this file in at the top, so it runs in the page's scope. Each
 * branch replays the page with a small inline redirect, exactly as it did inline.
 *
 * The handler bodies below are lifted verbatim from view_project.php: the queries
 * and the messages are unchanged.
 */

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    return;
}
if (defined('OCP_VIEW_PROJECT_ACTIONS_RAN')) {
    return;
}
define('OCP_VIEW_PROJECT_ACTIONS_RAN', true);

// This file runs before the endpoint, which is where the page's database
// connection now lives, so it opens its own. The endpoint reuses it.
$ocp_dir = __DIR__;
for ($ocp_i = 0; $ocp_i < 4 && !is_file($ocp_dir . '/config/db_config.php'); $ocp_i++) {
    $ocp_parent = dirname($ocp_dir);
    if ($ocp_parent === $ocp_dir) {
        break;
    }
    $ocp_dir = $ocp_parent;
}
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($pdo)) {
    require_once $ocp_dir . '/config/db_config.php';
}
unset($ocp_dir, $ocp_i, $ocp_parent);

// Which project the actions act on. The same rule the page used: the posted id, or
// the one remembered in the session. This runs here because the handlers below need
// it, and the endpoint reuses the value rather than deciding again.
if (!isset($_POST['id']) || empty($_POST['id'])) {
    if (!isset($_SESSION['current_project_id'])) {
        header('Location: projects.php');
        exit();
    }
    $project_id = $_SESSION['current_project_id'];
} else {
    $project_id = $_POST['id'];
    $_SESSION['current_project_id'] = $project_id;
}

// ---------------------------------------------------------------- add_workers
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_workers'])) {
    if (isset($_POST['worker_ids']) && is_array($_POST['worker_ids'])) {
        try {
            // Prepare the insert statement
            $insertStmt = $pdo->prepare("
                INSERT INTO project_workers (project_id, user_id, assigned_date) 
                VALUES (:project_id, :user_id, NOW())
            ");
            
            // Add each selected worker
            foreach ($_POST['worker_ids'] as $worker_id) {
                // Check if this worker is already assigned to the project
                $checkStmt = $pdo->prepare("
                    SELECT id FROM project_workers 
                    WHERE project_id = :project_id AND user_id = :user_id
                ");
                $checkStmt->bindParam(':project_id', $project_id);
                $checkStmt->bindParam(':user_id', $worker_id);
                $checkStmt->execute();
                
                if ($checkStmt->rowCount() === 0) {
                    // Worker not already assigned, so add them
                    $insertStmt->bindParam(':project_id', $project_id);
                    $insertStmt->bindParam(':user_id', $worker_id);
                    $insertStmt->execute();
                }
            }
            
            // Refresh the page to show the updated worker list
            echo "<script>window.location.href = 'view_project.php';</script>";
            exit();
        } catch(PDOException $e) {
            $add_worker_error = "Error adding workers: " . $e->getMessage();
        }
    } else {
        $add_worker_error = "Please select at least one worker to add.";
    }
}

// ---------------------------------------------------------------- add_rental
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_rental'])) {
    $rental_type = $_POST['rental_type'];
    $item_id = $_POST['item_id'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $rate = $_POST['rate'];
    $rate_type = $_POST['rate_type'];
    $notes = $_POST['notes'];
    
    // Calculate total cost based on rate type
    if ($rate_type === 'daily') {
        // Calculate days between start and end date
        $start = new DateTime($start_date);
        $end = new DateTime($end_date);
        $interval = $start->diff($end);
        $days = $interval->days + 1; // Include both start and end dates
        $total_cost = $days * $rate;
    } else { // hourly
        // For hourly, we need to calculate hours between start and end datetime
        // Assuming we have datetime inputs for hourly calculation
        $start = new DateTime($start_date);
        $end = new DateTime($end_date);
        $interval = $start->diff($end);
        $hours = $interval->h + ($interval->days * 24);
        $total_cost = $hours * $rate;
    }
    
    try {
        // Prepare the insert statement
        $insertStmt = $pdo->prepare("
            INSERT INTO project_rentals (project_id, vehicle_id, equipment_id, start_date, end_date, rate, rate_type, total_cost, notes) 
            VALUES (:project_id, :vehicle_id, :equipment_id, :start_date, :end_date, :rate, :rate_type, :total_cost, :notes)
        ");
        
        $vehicle_id = ($rental_type == 'vehicle') ? $item_id : null;
        $equipment_id = ($rental_type == 'equipment') ? $item_id : null;
        
        $insertStmt->bindParam(':project_id', $project_id);
        $insertStmt->bindParam(':vehicle_id', $vehicle_id);
        $insertStmt->bindParam(':equipment_id', $equipment_id);
        $insertStmt->bindParam(':start_date', $start_date);
        $insertStmt->bindParam(':end_date', $end_date);
        $insertStmt->bindParam(':rate', $rate);
        $insertStmt->bindParam(':rate_type', $rate_type);
        $insertStmt->bindParam(':total_cost', $total_cost);
        $insertStmt->bindParam(':notes', $notes);
        
        $insertStmt->execute();
        
        // Refresh the page to show the updated rentals list
        echo "<script>window.location.href = 'view_project.php';</script>";
        exit();
    } catch(PDOException $e) {
        $add_rental_error = "Error adding rental: " . $e->getMessage();
    }
}

// ---------------------------------------------------------------- remove_worker
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_worker'])) {
    $worker_id = $_POST['remove_worker'];
    
    try {
        $removeStmt = $pdo->prepare("
            DELETE FROM project_workers 
            WHERE project_id = :project_id AND user_id = :user_id
        ");
        $removeStmt->bindParam(':project_id', $project_id);
        $removeStmt->bindParam(':user_id', $worker_id);
        $removeStmt->execute();
        
        // Refresh the page to show the updated worker list
        echo "<script>window.location.href = 'view_project.php';</script>";
        exit();
    } catch(PDOException $e) {
        $remove_worker_error = "Error removing worker: " . $e->getMessage();
    }
}

// ---------------------------------------------------------------- remove_rental
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_rental'])) {
    $rental_id = $_POST['remove_rental'];
    
    try {
        $removeStmt = $pdo->prepare("
            DELETE FROM project_rentals 
            WHERE id = :id AND project_id = :project_id
        ");
        $removeStmt->bindParam(':id', $rental_id);
        $removeStmt->bindParam(':project_id', $project_id);
        $removeStmt->execute();
        
        // Refresh the page to show the updated rentals list
        echo "<script>window.location.href = 'view_project.php';</script>";
        exit();
    } catch(PDOException $e) {
        $remove_rental_error = "Error removing rental: " . $e->getMessage();
    }
}


