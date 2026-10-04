<?php
/**
 * includes/gasoline_purchase_order-functions.php
 *
 * The two helpers gasoline_purchase_order.php and its actions file share:
 * getDriverOperatorName(), which renders a line's driver or operator, and
 * generatePONumber(), which builds the next PO number with a duplicate check.
 *
 * generatePONumber() is called by the page's template while rendering the form, and
 * the handlers call both, so neither entry point can own them. They must be loaded
 * before whichever runs first, which is why
 * actions/gasoline_purchase_order-actions.php and
 * api/gasoline_purchase_order-endpoint.php both require this file.
 *
 * The bodies below are lifted verbatim from gasoline_purchase_order.php.
 */

if (defined('OCP_GASOLINE_PURCHASE_ORDER_FUNCTIONS_LOADED')) {
    return;
}
define('OCP_GASOLINE_PURCHASE_ORDER_FUNCTIONS_LOADED', true);

// Helper function to get driver/operator display name (for driver_operator column)
function getDriverOperatorName($item, $pdo) {
    // If manual driver name is provided, return it (will go to manual_driver_name column instead)
    if (!empty($item['manual_driver_name'])) {
        return null; // Return null for driver_operator column, manual_driver_name will be used
    }
    
    // If driver_operator_id is provided, get employee name
    if (!empty($item['driver_operator_id'])) {
        try {
            $stmt = $pdo->prepare("SELECT firstname, middlename, lastname, suffix FROM employee WHERE id = :id");
            $stmt->bindParam(':id', $item['driver_operator_id']);
            $stmt->execute();
            $employee = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($employee) {
                $name = $employee['firstname'];
                if (!empty($employee['middlename'])) {
                    $name .= ' ' . substr($employee['middlename'], 0, 1) . '.';
                }
                $name .= ' ' . $employee['lastname'];
                if (!empty($employee['suffix'])) {
                    $name .= ' ' . $employee['suffix'];
                }
                return $name;
            }
        } catch (PDOException $e) {
            return null;
        }
    }
    
    return null;
}


// Generate next PO number in format "000001" with proper duplicate check
function generatePONumber() {
    global $pdo;
    
    try {
        // Get the maximum PO number from the database
        $stmt = $pdo->prepare("SELECT po_number FROM gasoline_purchase_orders ORDER BY CAST(po_number AS UNSIGNED) DESC LIMIT 1");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && !empty($result['po_number'])) {
            // Extract numeric value from po_number (remove leading zeros)
            $last_number = intval($result['po_number']);
            $next_number = $last_number + 1;
        } else {
            // If no PO exists yet, start from 1
            $next_number = 1;
        }
        
        // Format as "000001" with leading zeros (6 digits)
        $po_number = str_pad($next_number, 6, '0', STR_PAD_LEFT);
        
        // Double-check that this PO number doesn't already exist (in case of race condition)
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM gasoline_purchase_orders WHERE po_number = :po_number");
        $checkStmt->bindParam(':po_number', $po_number);
        $checkStmt->execute();
        $exists = $checkStmt->fetchColumn();
        
        // If it somehow exists, increment until we find a unique number
        while ($exists > 0) {
            $next_number++;
            $po_number = str_pad($next_number, 6, '0', STR_PAD_LEFT);
            $checkStmt->bindParam(':po_number', $po_number);
            $checkStmt->execute();
            $exists = $checkStmt->fetchColumn();
        }
        
        return $po_number;
        
    } catch(PDOException $e) {
        // Fallback in case of error - generate a random 6-digit number
        return str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT);
    }
}
