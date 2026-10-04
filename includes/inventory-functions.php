<?php
/**
 * includes/inventory-functions.php
 *
 * The two helpers inventory.php and its actions file share: updateInventoryTable(),
 * which recomputes one item's stock in one warehouse from its movements, and
 * updateAllInventory(), which does that for everything.
 *
 * They live in their own file because both entry points need them. The page calls
 * updateAllInventory() before it reads, and every movement handler calls one or both
 * after it writes. They must therefore be loaded before whichever runs first, which
 * is why actions/inventory-actions.php and api/inventory-endpoint.php both require
 * this file.
 *
 * The bodies below are lifted verbatim from inventory.php.
 */

if (defined('OCP_INVENTORY_FUNCTIONS_LOADED')) {
    return;
}
define('OCP_INVENTORY_FUNCTIONS_LOADED', true);

// Function to update inventory table
function updateInventoryTable($pdo, $item_id, $warehouse_id) {
    try {
        // Calculate current inventory from batches
        $calcStmt = $pdo->prepare("
            SELECT 
                SUM(quantity) as total_quantity,
                CASE 
                    WHEN SUM(quantity) > 0 THEN SUM(quantity * unit_cost) / SUM(quantity)
                    ELSE 0
                END as avg_unit_cost,
                SUM(quantity * unit_cost) as total_value
            FROM inventory_batches 
            WHERE item_id = :item_id AND warehouse_id = :warehouse_id
        ");
        $calcStmt->bindParam(':item_id', $item_id);
        $calcStmt->bindParam(':warehouse_id', $warehouse_id);
        $calcStmt->execute();
        $inventory_data = $calcStmt->fetch(PDO::FETCH_ASSOC);
        
        $quantity = $inventory_data['total_quantity'] ?? 0;
        $unit_cost = $inventory_data['avg_unit_cost'] ?? 0;
        $total_value = $inventory_data['total_value'] ?? 0;
        
        // Check if record exists in inventory table
        $checkStmt = $pdo->prepare("SELECT id FROM inventory WHERE item_id = :item_id AND warehouse_id = :warehouse_id");
        $checkStmt->bindParam(':item_id', $item_id);
        $checkStmt->bindParam(':warehouse_id', $warehouse_id);
        $checkStmt->execute();
        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            // Update existing record
            $updateStmt = $pdo->prepare("
                UPDATE inventory 
                SET quantity = :quantity, unit_cost = :unit_cost, total_value = :total_value, last_updated = CURRENT_TIMESTAMP
                WHERE item_id = :item_id AND warehouse_id = :warehouse_id
            ");
            $updateStmt->bindParam(':quantity', $quantity);
            $updateStmt->bindParam(':unit_cost', $unit_cost);
            $updateStmt->bindParam(':total_value', $total_value);
            $updateStmt->bindParam(':item_id', $item_id);
            $updateStmt->bindParam(':warehouse_id', $warehouse_id);
            $updateStmt->execute();
        } else {
            // Insert new record
            $insertStmt = $pdo->prepare("
                INSERT INTO inventory (item_id, warehouse_id, quantity, unit_cost, total_value)
                VALUES (:item_id, :warehouse_id, :quantity, :unit_cost, :total_value)
            ");
            $insertStmt->bindParam(':item_id', $item_id);
            $insertStmt->bindParam(':warehouse_id', $warehouse_id);
            $insertStmt->bindParam(':quantity', $quantity);
            $insertStmt->bindParam(':unit_cost', $unit_cost);
            $insertStmt->bindParam(':total_value', $total_value);
            $insertStmt->execute();
        }
        
        return true;
    } catch(PDOException $e) {
        error_log("Error updating inventory table: " . $e->getMessage());
        return false;
    }
}

// Function to update all inventory records
function updateAllInventory($pdo) {
    try {
        // Get all unique item-warehouse combinations from batches
        $combinationsStmt = $pdo->prepare("
            SELECT DISTINCT item_id, warehouse_id 
            FROM inventory_batches 
            WHERE quantity > 0
            UNION
            SELECT DISTINCT item_id, warehouse_id 
            FROM inventory
        ");
        $combinationsStmt->execute();
        $combinations = $combinationsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($combinations as $combo) {
            updateInventoryTable($pdo, $combo['item_id'], $combo['warehouse_id']);
        }
        
        // Also remove inventory records where quantity is 0 and no batches exist
        $cleanupStmt = $pdo->prepare("
            DELETE FROM inventory 
            WHERE quantity = 0 
            AND NOT EXISTS (
                SELECT 1 FROM inventory_batches 
                WHERE inventory_batches.item_id = inventory.item_id 
                AND inventory_batches.warehouse_id = inventory.warehouse_id
            )
        ");
        $cleanupStmt->execute();
        
        return true;
    } catch(PDOException $e) {
        error_log("Error updating all inventory: " . $e->getMessage());
        return false;
    }
}

