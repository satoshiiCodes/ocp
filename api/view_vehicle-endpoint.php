<?php
/**
 * api/view_vehicle-endpoint.php
 *
 * Every read for view_vehicle.php lives in this one file: the vehicle, plus its
 * fuel movements, spare-parts replacements and rentals.
 *
 * The page pulls this in instead of querying the database itself, so all of the
 * page's fetching is in one place. It runs in the page's scope and returns an
 * array of the variables the markup needs; the page unpacks that array. Nothing
 * is printed here, so this file cannot disturb the page's output.
 *
 * Requested on its own, with no page behind it, there is no connection and no
 * session: the guard below then returns the empty result instead of erroring. It is only
 * reached once the actions file has worked out which vehicle to show.
 *
 * Returns
 *   vehicle              array  the vehicle being viewed
 *   fuel_records         array  its fuel movements, newest first
 *   spare_parts_records  array  its spare-parts issues, newest first
 *   rental_records       array  its project rentals, newest first
 *   message              string a lookup failure, so the page can show it
 *   message_type         string the alert style for that message
 */

$ocp_endpoint = [
    'vehicle' => null,
    'fuel_records' => [],
    'spare_parts_records' => [],
    'rental_records' => [],
    'message' => '',
    'message_type' => '',
];

// Standalone guard: see the note above.
if (!isset($pdo)) {
    return $ocp_endpoint;
}

// Set by the actions file, in the scope this file shares with the page.
$vehicle_id = $ocp_vehicle_id;

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

        // Fuel movements
        $stmt = $pdo->prepare("
            SELECT gasoline_type, quantity_liters, price_per_liter, movement_date, driver_operator, manual_driver_name 
            FROM gasoline_movements 
            WHERE vehicle_id = :vehicle_id 
            ORDER BY movement_date DESC, created_at DESC
        ");
        $stmt->bindParam(':vehicle_id', $vehicle_id);
        $stmt->execute();
        $ocp_endpoint['fuel_records'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Spare-parts replacements, with the technician's name
        $stmt = $pdo->prepare("
            SELECT 
                sp.part_name,
                sp.unit_of_measure,
                spm.quantity,
                spm.price_per_unit,
                spm.movement_date,
                spm.technician,
                spm.purpose,
                e.firstname,
                e.middlename,
                e.lastname,
                e.suffix
            FROM spare_parts_movements spm
            INNER JOIN spare_parts sp ON spm.part_id = sp.id
            LEFT JOIN employee e ON spm.technician = e.id
            WHERE spm.vehicle_id = :vehicle_id 
            AND spm.movement_type = 'out'
            ORDER BY spm.movement_date DESC, spm.created_at DESC
        ");
        $stmt->bindParam(':vehicle_id', $vehicle_id);
        $stmt->execute();
        $ocp_endpoint['spare_parts_records'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Rentals
        $stmt = $pdo->prepare("
            SELECT 
                pr.id,
                p.project_name,
                pr.start_date,
                pr.end_date,
                pr.rate,
                pr.rate_type,
                pr.total_cost,
                pr.notes,
                pr.created_at
            FROM project_rentals pr
            INNER JOIN projects p ON pr.project_id = p.id
            WHERE pr.vehicle_id = :vehicle_id
            ORDER BY pr.start_date DESC, pr.created_at DESC
        ");
        $stmt->bindParam(':vehicle_id', $vehicle_id);
        $stmt->execute();
        $ocp_endpoint['rental_records'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $ocp_endpoint['message'] = 'Error fetching vehicle details: ' . $e->getMessage();
    $ocp_endpoint['message_type'] = 'danger';
    $ocp_endpoint['vehicle'] = null;
}

return $ocp_endpoint;
