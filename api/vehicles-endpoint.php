<?php
/**
 * api/vehicles-endpoint.php
 *
 * Every read for vehicles.php lives in this one file: the vehicle listing.
 *
 * The page pulls this in instead of querying the database itself, so all of the
 * page's fetching is in one place. It runs in the page's scope and returns an
 * array of the variables the markup needs; the page unpacks that array. Nothing
 * is printed here, so this file cannot disturb the page's output.
 *
 * Requested on its own, with no page behind it, there is no connection and no
 * session: the guard below then returns the empty result instead of erroring.
 *
 * Returns
 *   vehicles   array  every vehicle, newest first
 */

$ocp_endpoint = [
    'vehicles' => [],
];

// Standalone guard: see the note above.
if (!isset($pdo)) {
    return $ocp_endpoint;
}

try {
    $vehiclesStmt = $pdo->prepare("SELECT * FROM vehicles ORDER BY created_at DESC");
    $vehiclesStmt->execute();
    $ocp_endpoint['vehicles'] = $vehiclesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['alert'] = [
        'type' => 'error',
        'title' => 'Database Error!',
        'text' => 'Error fetching vehicles: ' . $e->getMessage(),
    ];
}

return $ocp_endpoint;
