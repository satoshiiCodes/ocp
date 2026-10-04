<?php
/**
 * api/fuel_records-endpoint.php
 *
 * Every read for fuel_records.php lives in this one file: the vehicle being
 * viewed and that vehicle's fuel movements.
 *
 * The page pulls this in instead of querying the database itself, so all of the
 * page's fetching is in one place. It runs in the page's scope and returns an
 * array of the variables the markup needs; the page unpacks that array. Nothing
 * is printed here, so this file cannot disturb the page's output.
 *
 * Requested on its own, with no page behind it, there is no connection and no
 * session: the guard below then returns the empty result instead of erroring. It is only
 * reached once the actions file has confirmed a vehicle id was posted.
 *
 * Returns
 *   vehicle        array  the vehicle whose records are shown
 *   fuel_records   array  that vehicle's movements, newest first
 *   message        string a lookup failure, so the page can show it
 *   message_type   string the alert style for that message
 */

$ocp_endpoint = [
    'vehicle' => null,
    'fuel_records' => [],
    'message' => '',
    'message_type' => '',
];

// Standalone guard: see the note above.
if (!isset($pdo)) {
    return $ocp_endpoint;
}

$vehicle_id = $_POST['vehicle_id'] ?? null;

try {
    $stmt = $pdo->prepare("SELECT * FROM vehicles WHERE id = :id");
    $stmt->bindParam(':id', $vehicle_id);
    $stmt->execute();
    $vehicle = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$vehicle) {
        $ocp_endpoint['message'] = 'Vehicle not found.';
        $ocp_endpoint['message_type'] = 'danger';
    } else {
        $ocp_endpoint['vehicle'] = $vehicle;
        $stmt = $pdo->prepare("
            SELECT gm.*, 
                   s.name as supplier_name,
                   t_from.tank_name as transfer_from_name,
                   t_to.tank_name as transfer_to_name
            FROM gasoline_movements gm
            LEFT JOIN suppliers s ON gm.supplier_id = s.id
            LEFT JOIN gasoline_tanks t_from ON gm.transfer_from = t_from.id
            LEFT JOIN gasoline_tanks t_to ON gm.transfer_to = t_to.id
            WHERE gm.vehicle_id = :vehicle_id 
            ORDER BY gm.movement_date DESC, gm.created_at DESC
        ");
        $stmt->bindParam(':vehicle_id', $vehicle_id);
        $stmt->execute();
        $ocp_endpoint['fuel_records'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $ocp_endpoint['message'] = 'Error fetching vehicle details: ' . $e->getMessage();
    $ocp_endpoint['message_type'] = 'danger';
    $ocp_endpoint['vehicle'] = null;
}

return $ocp_endpoint;
