<?php
/**
 * api/gasoline_inventory-endpoint.php
 *
 * Every read for gasoline_inventory lives in this one file. The page pulls this in instead of
 * querying the database itself, so all of its fetching is in one place; it runs in
 * the page's scope and returns the variables the markup needs, which the page unpacks.
 * Nothing is printed here, so this file cannot disturb the page's output.
 *
 * The block is lifted verbatim from gasoline_inventory: the queries and the filters are unchanged.
 *
 * Returns
 *   gasolineTypes
 *   suppliers
 *   tanks
 *   vehicles
 *   equipment
 *   gasoline_inventory
 *   low_gas_items
 *   gasoline_movements
 *   gasoline_batches
 */

$ocp_endpoint = [
    'gasolineTypes' => [],
    'suppliers' => [],
    'tanks' => [],
    'vehicles' => [],
    'equipment' => [],
    'gasoline_inventory' => [],
    'low_gas_items' => [],
    'gasoline_movements' => [],
    'gasoline_batches' => [],
    // Seeded from the actions file, so a message it set is not wiped out by the
    // endpoint running after it; the block below overwrites it only on a fetch failure.
    'swal_data' => $swal_data ?? [],
];

// Standalone guard: the block runs in the page's scope, where the connection is already
// open. Requested on its own there is none, so it answers with the empty result.
if (!isset($pdo)) {
    return $ocp_endpoint;
}

// Fetch data for dropdowns and tables
try {
    // Gasoline types
    $ocp_endpoint['gasolineTypes'] = ['Unleaded', 'Premium', 'Diesel'];
    
    // Get suppliers
    $suppliersStmt = $pdo->prepare("SELECT id, supplier_name FROM gasoline_suppliers WHERE supplier_type = 'fuel' OR supplier_type IS NULL ORDER BY supplier_name");
    $suppliersStmt->execute();
    $ocp_endpoint['suppliers'] = $suppliersStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get tanks
    $tanksStmt = $pdo->prepare("SELECT id, tank_name, location, capacity_liters FROM gasoline_tanks ORDER BY tank_name");
    $tanksStmt->execute();
    $ocp_endpoint['tanks'] = $tanksStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get vehicles
    $vehiclesStmt = $pdo->prepare("SELECT id, vehicle_name, plate_number FROM vehicles WHERE fuel_type = 'gasoline' OR fuel_type = 'diesel' ORDER BY vehicle_name");
    $vehiclesStmt->execute();
    $ocp_endpoint['vehicles'] = $vehiclesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get equipment
    $equipmentStmt = $pdo->prepare("SELECT id, equipment_name FROM equipment WHERE fuel_type = 'gasoline' OR fuel_type = 'diesel' ORDER by equipment_name");
    $equipmentStmt->execute();
    $ocp_endpoint['equipment'] = $equipmentStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get gasoline inventory summary with minimum level check
    // Calculate weighted average price per liter for each gasoline type in each tank
    $inventoryStmt = $pdo->prepare("
        SELECT 
            gi.gasoline_type, 
            gi.tank_id, 
            gi.quantity_liters, 
            gi.price_per_liter,
            gt.tank_name, 
            gt.location, 
            gt.capacity_liters,
            COALESCE(gml.min_stock_liters, 0) as min_stock_liters,
            CASE 
                WHEN gi.quantity_liters <= 0 THEN 'out-of-stock'
                WHEN gi.quantity_liters <= COALESCE(gml.min_stock_liters, 0) AND COALESCE(gml.min_stock_liters, 0) > 0 THEN 'low-stock'
                ELSE 'normal'
            END AS stock_status
        FROM gasoline_inventory gi
        JOIN gasoline_tanks gt ON gi.tank_id = gt.id
        LEFT JOIN gasoline_min_levels gml ON gi.tank_id = gml.tank_id AND gi.gasoline_type = gml.gasoline_type
        ORDER BY gi.gasoline_type, gt.tank_name
    ");
    $inventoryStmt->execute();
    $ocp_endpoint['gasoline_inventory'] = $inventoryStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get low gasoline alerts
    $lowGasStmt = $pdo->prepare("
        SELECT gi.gasoline_type, gi.tank_id, gi.quantity_liters, gi.price_per_liter,
               gt.tank_name, gt.location, gt.capacity_liters,
               gml.min_stock_liters
        FROM gasoline_inventory gi
        JOIN gasoline_tanks gt ON gi.tank_id = gt.id
        JOIN gasoline_min_levels gml ON gi.tank_id = gml.tank_id AND gi.gasoline_type = gml.gasoline_type
        WHERE gi.quantity_liters <= gml.min_stock_liters
        ORDER BY gi.quantity_liters ASC, gi.gasoline_type
    ");
    $lowGasStmt->execute();
    $ocp_endpoint['low_gas_items'] = $lowGasStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent gasoline movements
    $movementsStmt = $pdo->prepare("
        SELECT gm.*, 
               s.supplier_name,
               v.vehicle_name, v.plate_number,
               e.equipment_name,
               gt.tank_name,
               gt_from.tank_name as from_tank_name,
               gt_to.tank_name as to_tank_name,
               gb.price_per_liter as batch_price
        FROM gasoline_movements gm
        LEFT JOIN gasoline_suppliers s ON gm.supplier_id = s.id
        LEFT JOIN vehicles v ON gm.vehicle_id = v.id
        LEFT JOIN equipment e ON gm.equipment_id = e.id
        LEFT JOIN gasoline_tanks gt ON gm.tank_id = gt.id
        LEFT JOIN gasoline_tanks gt_from ON gm.transfer_from = gt_from.id
        LEFT JOIN gasoline_tanks gt_to ON gm.transfer_to = gt_to.id
        LEFT JOIN gasoline_batches gb ON gm.batch_id = gb.id
        ORDER BY gm.movement_date DESC, gm.created_at DESC
        LIMIT 50
    ");
    $movementsStmt->execute();
    $ocp_endpoint['gasoline_movements'] = $movementsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get gasoline batches for FIFO tracking
    $batchesStmt = $pdo->prepare("
        SELECT gb.*, gt.tank_name, gt.location, s.supplier_name
        FROM gasoline_batches gb
        JOIN gasoline_tanks gt ON gb.tank_id = gt.id
        LEFT JOIN gasoline_suppliers s ON gb.supplier_id = s.id
        WHERE gb.quantity_liters > 0
        ORDER BY gb.gasoline_type, gb.tank_id, gb.date_received DESC, gb.id DESC
    ");
    $batchesStmt->execute();
    $ocp_endpoint['gasoline_batches'] = $batchesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $ocp_endpoint['swal_data'] = array(
        'title' => 'Error!',
        'text' => 'Error fetching data: ' . $e->getMessage(),
        'icon' => 'error'
    );
}

return $ocp_endpoint;
