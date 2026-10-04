<?php
/**
 * api/issue_materials-endpoint.php
 *
 * Every read for issue_materials.php lives in this one file: the materials that
 * can be issued, the active employees, the recent issuances, the stock summary,
 * the dropdown markup the page's script needs, and the signed-in user's name.
 *
 * The page pulls this in instead of querying the database itself, so all of the
 * page's fetching is in one place. It runs in the page's scope and returns an
 * array of the variables the markup needs; the page unpacks that array. Nothing
 * is printed here, so this file cannot disturb the page's output.
 *
 * Returns
 *   parts                 array  issuable materials, with stock and FIFO price
 *   employees             array  the active employees who can receive materials
 *   materials_issued      array  the 50 most recent issuances
 *   parts_inventory       array  the Materials stock summary
 *   materialOptionsHtml   string the <option> list the page's script inserts
 *   display_name          string the signed-in user's name, for the side menu
 */

$ocp_endpoint = [
    'parts' => [],
    'employees' => [],
    'materials_issued' => [],
    'parts_inventory' => [],
    'materialOptionsHtml' => '',
];

// Standalone guard: see the note above.
if (!isset($pdo)) {
    return $ocp_endpoint;
}

try {
    // Materials only, with the stock on hand and the next FIFO price
    $partsStmt = $pdo->prepare("
        SELECT sp.id, sp.part_number, sp.part_name, sp.description, spc.category_name, sp.unit_of_measure,
               COALESCE(spi.quantity, 0) as quantity, 
               COALESCE(spi.price_per_unit, 0) as current_price,
               (
                   SELECT price_per_unit 
                   FROM spare_parts_batches 
                   WHERE part_id = sp.id AND quantity > 0 
                   ORDER BY date_received ASC, id ASC 
                   LIMIT 1
               ) as next_fifo_price
        FROM spare_parts sp
        JOIN spare_parts_categories spc ON sp.category_id = spc.id
        LEFT JOIN spare_parts_inventory spi ON sp.id = spi.part_id
        WHERE COALESCE(spi.quantity, 0) > 0
        AND spc.category_name = 'Materials'
        ORDER BY sp.part_name
    ");
    $partsStmt->execute();
    $ocp_endpoint['parts'] = $partsStmt->fetchAll(PDO::FETCH_ASSOC);

    // The employees who can receive materials
    $employeesStmt = $pdo->prepare("
        SELECT id, employee_id, firstname, middlename, lastname, suffix, position 
        FROM employee 
        WHERE status = 'active' 
        ORDER BY lastname, firstname
    ");
    $employeesStmt->execute();
    $ocp_endpoint['employees'] = $employeesStmt->fetchAll(PDO::FETCH_ASSOC);

    // The most recent issuances
    $issuedStmt = $pdo->prepare("
        SELECT emi.*, 
               e.employee_id as emp_id, 
               CONCAT(e.firstname, ' ', e.lastname) as employee_name,
               e.position,
               sp.part_number, sp.part_name,
               spc.category_name
        FROM employee_materials_issued emi
        JOIN employee e ON emi.employee_id = e.id
        JOIN spare_parts sp ON emi.part_id = sp.id
        JOIN spare_parts_categories spc ON sp.category_id = spc.id
        ORDER BY emi.date_issued DESC, emi.created_at DESC
        LIMIT 50
    ");
    $issuedStmt->execute();
    $ocp_endpoint['materials_issued'] = $issuedStmt->fetchAll(PDO::FETCH_ASSOC);

    // The Materials stock summary
    $inventoryStmt = $pdo->prepare("
        SELECT 
            sp.id as part_id, 
            COALESCE(spi.quantity, 0) as quantity, 
            COALESCE(spi.price_per_unit, 0) as current_price,
            sp.part_number,
            sp.part_name,
            sp.description,
            spc.category_name,
            sp.unit_of_measure
        FROM spare_parts sp
        JOIN spare_parts_categories spc ON sp.category_id = spc.id
        LEFT JOIN spare_parts_inventory spi ON sp.id = spi.part_id
        WHERE COALESCE(spi.quantity, 0) > 0
        AND spc.category_name = 'Materials'
        ORDER BY sp.part_name
    ");
    $inventoryStmt->execute();
    $ocp_endpoint['parts_inventory'] = $inventoryStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $ocp_endpoint['swal_data'] = [
        'title' => 'Error!',
        'text' => 'Error fetching data: ' . $e->getMessage(),
        'icon' => 'error',
    ];
}

/* The material dropdown is built by the page's script, which the browser fetches
 * as its own request where $parts is not in scope. The options are therefore
 * rendered here and handed over as ready-made markup. */
$ocp_endpoint['materialOptionsHtml'] = implode('', array_map(
    static function ($part) {
        return '<option value="' . htmlspecialchars($part['id'], ENT_QUOTES) . '"'
            . ' data-quantity="' . htmlspecialchars($part['quantity'], ENT_QUOTES) . '"'
            . ' data-next-fifo-price="' . htmlspecialchars($part['next_fifo_price'], ENT_QUOTES) . '"'
            . ' data-part-number="' . htmlspecialchars($part['part_number'], ENT_QUOTES) . '"'
            . ' data-part-name="' . htmlspecialchars($part['part_name'], ENT_QUOTES) . '"'
            . ' data-unit="' . htmlspecialchars($part['unit_of_measure'], ENT_QUOTES) . '">'
            . htmlspecialchars($part['part_number'] . ' - ' . $part['part_name'] . ' (' . $part['category_name'] . ')', ENT_QUOTES)
            . '</option>';
    },
    $ocp_endpoint['parts']
));

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
