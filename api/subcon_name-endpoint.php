<?php
/**
 * api/subcons-endpoint.php
 *
 * Every read for subcons.php lives in this one file: the subcontractor listing.
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
 *   subcons   array  every subcontractor, newest first
 */

$ocp_endpoint = [
    'subcons' => [],
];

// Standalone guard: see the note above.
if (!isset($pdo)) {
    return $ocp_endpoint;
}

try {
    $subconsStmt = $pdo->prepare("SELECT * FROM subcons ORDER BY created_at DESC");
    $subconsStmt->execute();
    $ocp_endpoint['subcons'] = $subconsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['swal_data'] = [
        'title' => 'Error!',
        'text' => 'Error fetching subcontractors: ' . $e->getMessage(),
        'icon' => 'error',
    ];
}

return $ocp_endpoint;
