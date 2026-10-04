<?php
/**
 * api/suppliers-endpoint.php
 *
 * Every read for suppliers.php lives in this one file: the supplier listing.
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
 *   suppliers   array  every supplier, newest first
 */

$ocp_endpoint = [
    'suppliers' => [],
];

// Standalone guard: see the note above.
if (!isset($pdo)) {
    return $ocp_endpoint;
}

try {
    $suppliersStmt = $pdo->prepare("SELECT * FROM suppliers ORDER BY created_at DESC");
    $suppliersStmt->execute();
    $ocp_endpoint['suppliers'] = $suppliersStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['swal_data'] = [
        'title' => 'Error!',
        'text' => 'Error fetching suppliers: ' . $e->getMessage(),
        'icon' => 'error',
    ];
}

return $ocp_endpoint;
