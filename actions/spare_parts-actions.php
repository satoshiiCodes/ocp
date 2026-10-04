<?php
/**
 * actions/spare_parts-actions.php
 *
 * Every action for spare_parts lives in this one file.
 *
 * The page pulls this file in at the top, so it runs in the page's scope: the
 * database handle, the session and $_POST behave exactly as they did when this
 * code sat inline. A rejected submission leaves its message set and the markup
 * below repopulates the form from $_POST; a success redirects.
 *
 * The block below is lifted verbatim from spare_parts: the queries, the messages and
 * the validation are unchanged.
 */

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    return;
}
if (defined('OCP_SPARE_PARTS_ACTIONS_RAN')) {
    return;
}
define('OCP_SPARE_PARTS_ACTIONS_RAN', true);

// The message variables the page's markup reads. Both are filled in below.
$message = '';
$message_type = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if it's a delete operation
    if (isset($_POST['delete_id'])) {
        $delete_id = $_POST['delete_id'];
        
        try {
            $deleteStmt = $pdo->prepare("DELETE FROM spare_parts WHERE id = :id");
            $deleteStmt->bindParam(':id', $delete_id);
            
            if ($deleteStmt->execute()) {
                $_SESSION['alert'] = [
                    'type' => 'success',
                    'title' => 'Success!',
                    'message' => 'Spare part deleted successfully!'
                ];
            } else {
                $_SESSION['alert'] = [
                    'type' => 'error',
                    'title' => 'Error!',
                    'message' => 'Error deleting spare part. Please try again.'
                ];
            }
        } catch(PDOException $e) {
            $_SESSION['alert'] = [
                'type' => 'error',
                'title' => 'Database Error!',
                'message' => 'Database error: ' . $e->getMessage()
            ];
        }
        header("Location: ".$_SERVER['PHP_SELF']);
        exit();
    } 
    // Check if it's an edit operation
    else if (isset($_POST['edit_id'])) {
        $edit_id = $_POST['edit_id'];
        $part_number = trim($_POST['part_number']);
        $part_name = trim($_POST['part_name']);
        $category_id = trim($_POST['category_id']);
        $unit_of_measure = trim($_POST['unit_of_measure']);
        $min_stock_level = isset($_POST['min_stock_level']) ? (int)$_POST['min_stock_level'] : 0;
        
        // Basic validation
        if (empty($part_number) || empty($part_name) || empty($category_id)) {
            $_SESSION['alert'] = [
                'type' => 'error',
                'title' => 'Validation Error!',
                'message' => 'Part number, part name, and category are required fields.'
            ];
            header("Location: ".$_SERVER['PHP_SELF']);
            exit();
        }
        
        // Validate min_stock_level is non-negative
        if ($min_stock_level < 0) {
            $_SESSION['alert'] = [
                'type' => 'error',
                'title' => 'Validation Error!',
                'message' => 'Minimum stock level cannot be negative.'
            ];
            header("Location: ".$_SERVER['PHP_SELF']);
            exit();
        }
        
        try {
            // Check if part number already exists (excluding current part)
            $checkStmt = $pdo->prepare("SELECT id FROM spare_parts WHERE part_number = :part_number AND id != :id");
            $checkStmt->bindParam(':part_number', $part_number);
            $checkStmt->bindParam(':id', $edit_id);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() > 0) {
                $_SESSION['alert'] = [
                    'type' => 'error',
                    'title' => 'Duplicate Entry!',
                    'message' => 'Part number already exists. Please use a different part number.'
                ];
                header("Location: ".$_SERVER['PHP_SELF']);
                exit();
            }
            
            // Update spare part
            $updateStmt = $pdo->prepare("UPDATE spare_parts SET part_number = :part_number, part_name = :part_name, category_id = :category_id, unit_of_measure = :unit_of_measure, min_stock_level = :min_stock_level WHERE id = :id");
            $updateStmt->bindParam(':part_number', $part_number);
            $updateStmt->bindParam(':part_name', $part_name);
            $updateStmt->bindParam(':category_id', $category_id);
            $updateStmt->bindParam(':unit_of_measure', $unit_of_measure);
            $updateStmt->bindParam(':min_stock_level', $min_stock_level);
            $updateStmt->bindParam(':id', $edit_id);
            
            if ($updateStmt->execute()) {
                $_SESSION['alert'] = [
                    'type' => 'success',
                    'title' => 'Success!',
                    'message' => 'Spare part updated successfully!'
                ];
            } else {
                $_SESSION['alert'] = [
                    'type' => 'error',
                    'title' => 'Error!',
                    'message' => 'Error updating spare part. Please try again.'
                ];
            }
        } catch(PDOException $e) {
            $_SESSION['alert'] = [
                'type' => 'error',
                'title' => 'Database Error!',
                'message' => 'Database error: ' . $e->getMessage()
            ];
        }
        header("Location: ".$_SERVER['PHP_SELF']);
        exit();
    }
    // It's an add operation
    else {
        $part_number = trim($_POST['part_number']);
        $part_name = trim($_POST['part_name']);
        $category_id = trim($_POST['category_id']);
        $unit_of_measure = trim($_POST['unit_of_measure']);
        $min_stock_level = isset($_POST['min_stock_level']) ? (int)$_POST['min_stock_level'] : 0;
        
        // Basic validation
        if (empty($part_number) || empty($part_name) || empty($category_id)) {
            $_SESSION['alert'] = [
                'type' => 'error',
                'title' => 'Validation Error!',
                'message' => 'Part number, part name, and category are required fields.'
            ];
            header("Location: ".$_SERVER['PHP_SELF']);
            exit();
        }
        
        // Validate min_stock_level is non-negative
        if ($min_stock_level < 0) {
            $_SESSION['alert'] = [
                'type' => 'error',
                'title' => 'Validation Error!',
                'message' => 'Minimum stock level cannot be negative.'
            ];
            header("Location: ".$_SERVER['PHP_SELF']);
            exit();
        }
        
        try {
            // Check if part number already exists
            $checkStmt = $pdo->prepare("SELECT id FROM spare_parts WHERE part_number = :part_number");
            $checkStmt->bindParam(':part_number', $part_number);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() > 0) {
                $_SESSION['alert'] = [
                    'type' => 'error',
                    'title' => 'Duplicate Entry!',
                    'message' => 'Part number already exists. Please use a different part number.'
                ];
                header("Location: ".$_SERVER['PHP_SELF']);
                exit();
            }
            
            // Insert new spare part
            $insertStmt = $pdo->prepare("INSERT INTO spare_parts (part_number, part_name, category_id, unit_of_measure, min_stock_level) VALUES (:part_number, :part_name, :category_id, :unit_of_measure, :min_stock_level)");
            $insertStmt->bindParam(':part_number', $part_number);
            $insertStmt->bindParam(':part_name', $part_name);
            $insertStmt->bindParam(':category_id', $category_id);
            $insertStmt->bindParam(':unit_of_measure', $unit_of_measure);
            $insertStmt->bindParam(':min_stock_level', $min_stock_level);
            
            if ($insertStmt->execute()) {
                $_SESSION['alert'] = [
                    'type' => 'success',
                    'title' => 'Success!',
                    'message' => 'Spare part added successfully!'
                ];
            } else {
                $_SESSION['alert'] = [
                    'type' => 'error',
                    'title' => 'Error!',
                    'message' => 'Error adding spare part. Please try again.'
                ];
            }
        } catch(PDOException $e) {
            $_SESSION['alert'] = [
                'type' => 'error',
                'title' => 'Database Error!',
                'message' => 'Database error: ' . $e->getMessage()
            ];
        }
        header("Location: ".$_SERVER['PHP_SELF']);
        exit();
    }
}
