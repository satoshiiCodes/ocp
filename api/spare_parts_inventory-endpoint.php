<?php
/**
 * api/spare_parts_inventory-endpoint.php
 *
 * Every read for spare_parts_inventory.php lives in this one file: the categories,
 * parts, suppliers, vehicles and equipment the forms offer, the stock summary, the
 * low-stock alerts, the recent movements and the open batches, plus the signed-in
 * user's display name.
 *
 * The page pulls this in instead of querying the database itself, so all of the
 * page's fetching is in one place. It runs in the page's scope and returns an
 * array of the variables the markup needs; the page unpacks that array. Nothing
 * is printed here, so this file cannot disturb the page's output.
 *
 * Returns
 *   categories       array  every parts category, for the form dropdowns
 *   parts            array  every spare part, for the form dropdowns
 *   suppliers        array  every spare-parts supplier
 *   vehicles         array  every vehicle
 *   equipment        array  every piece of equipment
 *   employees        array  every employee, for the technician dropdown
 *   parts_inventory  array  the stock summary, with each part's stock status
 *   low_parts_items  array  the parts at or below their minimum level
 *   parts_movements  array  the 50 most recent movements
 *   parts_batches    array  the batches that still have quantity, oldest first
 *   display_name     string the signed-in user's name, for the side menu
 */

// ---------------------------------------------------------------------------
// The single-movement lookup the page's JavaScript performs: ?id=<movement id>.
// It answers JSON instead of the array below, and it is its own request, so the
// page's login guard and its database handle are not in scope: both are set up
// here. The SELECT and its joins are the movements listing's, so the fields the
// script reads are the fields the table was rendered from. Lifted from
// api/inventory-endpoint.php, which does the same for stock movements.
// ---------------------------------------------------------------------------
if (isset($_GET['id'])) {
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

    header('Content-Type: application/json');

    if (!isset($_SESSION['user_id'])) {
        header('HTTP/1.1 401 Unauthorized');
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }

    $ocp_movement_id = (int) ($_GET['id'] ?? 0);

    try {
        $ocp_movement_stmt = $pdo->prepare("
            SELECT spm.*, 
                s.supplier_name,
                v.vehicle_name, v.plate_number,
                e.equipment_name,
                sp.part_number, sp.part_name,
                spb.price_per_unit as batch_price,
                spb.batch_number,
                spb.purchase_request,
                emp.firstname, emp.middlename, emp.lastname, emp.suffix,
                tech.firstname as tech_firstname, 
                tech.middlename as tech_middlename, 
                tech.lastname as tech_lastname, 
                tech.suffix as tech_suffix
            FROM spare_parts_movements spm
            LEFT JOIN spare_parts_suppliers s ON spm.supplier_id = s.id
            LEFT JOIN vehicles v ON spm.vehicle_id = v.id
            LEFT JOIN equipment e ON spm.equipment_id = e.id
            LEFT JOIN spare_parts sp ON spm.part_id = sp.id
            LEFT JOIN spare_parts_batches spb ON spm.batch_id = spb.id
            LEFT JOIN employee emp ON spm.employee_id = emp.id
            LEFT JOIN employee tech ON spm.technician = tech.id
            WHERE spm.id = :id
        ");
        $ocp_movement_stmt->bindValue(':id', $ocp_movement_id, PDO::PARAM_INT);
        $ocp_movement_stmt->execute();
        $ocp_movement = $ocp_movement_stmt->fetch(PDO::FETCH_ASSOC);

        if ($ocp_movement) {
            echo json_encode(['success' => true, 'movement' => $ocp_movement]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Movement not found']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }

    unset($ocp_movement_id, $ocp_movement_stmt, $ocp_movement);

    exit();
}

$ocp_endpoint = [
    'categories' => [],
    'parts' => [],
    'suppliers' => [],
    'vehicles' => [],
    'equipment' => [],
    'employees' => [],
    'parts_inventory' => [],
    'low_parts_items' => [],
    'parts_movements' => [],
    'parts_batches' => [],
];

// Standalone guard: see the note above.
if (!isset($pdo)) {
    return $ocp_endpoint;
}

try {
    // The categories the forms offer
    $categoriesStmt = $pdo->prepare("SELECT id, category_name FROM spare_parts_categories ORDER BY category_name");
    $categoriesStmt->execute();
    $ocp_endpoint['categories'] = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);

    // The parts the forms offer
    $partsStmt = $pdo->prepare("
        SELECT sp.id, sp.part_number, sp.part_name, sp.description, spc.category_name, sp.unit_of_measure, sp.min_stock_level
        FROM spare_parts sp
        JOIN spare_parts_categories spc ON sp.category_id = spc.id
        ORDER BY sp.part_name
    ");
    $partsStmt->execute();
    $ocp_endpoint['parts'] = $partsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Suppliers, vehicles and equipment
    $suppliersStmt = $pdo->prepare("SELECT id, supplier_name FROM spare_parts_suppliers ORDER BY supplier_name");
    $suppliersStmt->execute();
    $ocp_endpoint['suppliers'] = $suppliersStmt->fetchAll(PDO::FETCH_ASSOC);

    $vehiclesStmt = $pdo->prepare("SELECT id, vehicle_name, plate_number FROM vehicles ORDER BY vehicle_name");
    $vehiclesStmt->execute();
    $ocp_endpoint['vehicles'] = $vehiclesStmt->fetchAll(PDO::FETCH_ASSOC);

    $equipmentStmt = $pdo->prepare("SELECT id, equipment_name FROM equipment ORDER BY equipment_name");
    $equipmentStmt->execute();
    $ocp_endpoint['equipment'] = $equipmentStmt->fetchAll(PDO::FETCH_ASSOC);

    // The employees the edit form offers as the movement's technician. Not filtered by
    // status: a movement may name an employee who has since been deactivated, and the
    // edit form has to be able to show the value the row already carries.
    $employeesStmt = $pdo->prepare("
        SELECT id, firstname, middlename, lastname, suffix
        FROM employee
        ORDER BY lastname, firstname, id
    ");
    $employeesStmt->execute();
    $ocp_endpoint['employees'] = $employeesStmt->fetchAll(PDO::FETCH_ASSOC);

    // The stock summary, with each part's status against its minimum level.
    //
    // "Avg Price/Unit" used to be the single price stored on the inventory row - the price of
    // whichever delivery last wrote it, which is not an average of anything.
    //
    // Quantity, Avg Price/Unit and Total Value are now all derived from the same place: the
    // batches the stock is actually held in. The average is their value divided by their
    // quantity, so the row reads Quantity x Avg Price/Unit = Total Value by construction, and
    // an average can never disagree with the figure printed beside it.
    //
    // Deriving the quantity here also settles a real disagreement rather than hiding it: on
    // part 13 the inventory row said 5 where the batches held 20, and an average of the
    // batches shown against the stored 5 cannot multiply out. The batches are what the FIFO
    // issue path draws from, so they are the stock. A part held in no batches falls back to
    // its inventory row, and the status is computed from the same figure that is shown, so
    // the badge can never contradict the number.
    $inventoryStmt = $pdo->prepare("
        SELECT
            sp.id as part_id,
            sp.part_number,
            sp.part_name,
            sp.description,
            spc.category_name,
            sp.unit_of_measure,
            COALESCE(sp.min_stock_level, 0) as min_stock_level,
            COALESCE(spi.price_per_unit, 0) as price_per_unit,
            COALESCE(stock.quantity, spi.quantity, 0) as quantity,
            COALESCE(stock.value, spi.quantity * spi.price_per_unit, 0) as stock_value,
            COALESCE(stock.avg_price,
                     CASE WHEN COALESCE(spi.quantity, 0) > 0 THEN spi.price_per_unit ELSE 0 END,
                     0) as avg_price_per_unit,
            CASE
                WHEN COALESCE(stock.quantity, spi.quantity, 0) <= 0 THEN 'out-of-stock'
                WHEN COALESCE(stock.quantity, spi.quantity, 0) <= COALESCE(sp.min_stock_level, 0)
                     AND COALESCE(sp.min_stock_level, 0) > 0 THEN 'low-stock'
                ELSE 'normal'
            END AS stock_status
        FROM spare_parts sp
        JOIN spare_parts_categories spc ON sp.category_id = spc.id
        LEFT JOIN spare_parts_inventory spi ON sp.id = spi.part_id
        LEFT JOIN (
            SELECT part_id,
                   SUM(quantity) AS quantity,
                   SUM(quantity * price_per_unit) AS value,
                   SUM(quantity * price_per_unit) / SUM(quantity) AS avg_price
            FROM spare_parts_batches
            WHERE quantity > 0
            GROUP BY part_id
        ) stock ON stock.part_id = sp.id
        ORDER BY sp.part_name
    ");
    $inventoryStmt->execute();
    $ocp_endpoint['parts_inventory'] = $inventoryStmt->fetchAll(PDO::FETCH_ASSOC);

    // The parts at or below their minimum level
    $lowPartsStmt = $pdo->prepare("
        SELECT spi.part_id, spi.quantity, spi.price_per_unit,
               sp.part_number, sp.part_name, sp.description, spc.category_name,
               sp.min_stock_level
        FROM spare_parts_inventory spi
        JOIN spare_parts sp ON spi.part_id = sp.id
        JOIN spare_parts_categories spc ON sp.category_id = spc.id
        WHERE spi.quantity <= sp.min_stock_level AND sp.min_stock_level > 0
        ORDER BY spi.quantity ASC, sp.part_name
    ");
    $lowPartsStmt->execute();
    $ocp_endpoint['low_parts_items'] = $lowPartsStmt->fetchAll(PDO::FETCH_ASSOC);

    // The most recent movements
    $movementsStmt = $pdo->prepare("
        SELECT spm.*, 
            s.supplier_name,
            v.vehicle_name, v.plate_number,
            e.equipment_name,
            sp.part_number, sp.part_name,
            spb.price_per_unit as batch_price,
            spb.batch_number,
            spb.purchase_request,
            emp.firstname, emp.middlename, emp.lastname, emp.suffix,
            tech.firstname as tech_firstname, 
            tech.middlename as tech_middlename, 
            tech.lastname as tech_lastname, 
            tech.suffix as tech_suffix
        FROM spare_parts_movements spm
        LEFT JOIN spare_parts_suppliers s ON spm.supplier_id = s.id
        LEFT JOIN vehicles v ON spm.vehicle_id = v.id
        LEFT JOIN equipment e ON spm.equipment_id = e.id
        LEFT JOIN spare_parts sp ON spm.part_id = sp.id
        LEFT JOIN spare_parts_batches spb ON spm.batch_id = spb.id
        LEFT JOIN employee emp ON spm.employee_id = emp.id
        LEFT JOIN employee tech ON spm.technician = tech.id
        ORDER BY spm.movement_date DESC, spm.created_at DESC
        LIMIT 50
    ");
    $movementsStmt->execute();
    $ocp_endpoint['parts_movements'] = $movementsStmt->fetchAll(PDO::FETCH_ASSOC);

    // The batches that still have quantity, oldest first, for FIFO
    $batchesStmt = $pdo->prepare("
        SELECT spb.*, sp.part_number, sp.part_name, s.supplier_name
        FROM spare_parts_batches spb
        JOIN spare_parts sp ON spb.part_id = sp.id
        LEFT JOIN spare_parts_suppliers s ON spb.supplier_id = s.id
        WHERE spb.quantity > 0
        -- Newest first: the table is read top-down, so the most recently received batch is
        -- the one worth seeing first. It was ASC (oldest first), which buried it.
        ORDER BY spb.part_id, spb.date_received DESC, spb.id DESC
    ");
    $batchesStmt->execute();
    $ocp_endpoint['parts_batches'] = $batchesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $ocp_endpoint['swal_data'] = [
        'title' => 'Error!',
        'text' => 'Error fetching data: ' . $e->getMessage(),
        'icon' => 'error',
    ];
}

// --- the signed-in user, whose name the side menu prints ---------------------
$ocp_user_id = $_SESSION['user_id'] ?? null;
$stmt = $pdo->prepare("SELECT firstname, middlename, lastname, suffix FROM users WHERE id = :id");
$stmt->bindParam(':id', $ocp_user_id);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    $display_name = $user['firstname'];
    if (!empty($user['middlename'])) {
        $display_name .= ' ' . substr($user['middlename'], 0, 1) . '.';
    }
    $display_name .= ' ' . $user['lastname'];
    if (!empty($user['suffix'])) {
        $display_name .= ' ' . $user['suffix'];
    }
    $ocp_endpoint['display_name'] = $display_name;
}
unset($ocp_user_id, $user, $display_name);

return $ocp_endpoint;
