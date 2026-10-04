<?php
/**
 * api/projects-endpoint.php
 *
 * Every read for projects.php lives in this one file: the project listing with
 * its engineers, and the list of Engineering users for the form's dropdown.
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
 *   projects       array  every project, with its engineers joined into one string
 *   engineers      array  the active Engineering users, for the add form
 *   error_message  string a fetch failure, so the page can show it
 */

$ocp_endpoint = [
    'projects' => [],
    'engineers' => [],
    'error_message' => '',
];

// Standalone guard: see the note above.
if (!isset($pdo)) {
    return $ocp_endpoint;
}

// --- the project listing -----------------------------------------------------
try {
    $projectsStmt = $pdo->prepare("
        SELECT p.*, GROUP_CONCAT(CONCAT(u.firstname, ' ', u.lastname) SEPARATOR ', ') as engineers 
        FROM projects p 
        LEFT JOIN project_engineers pe ON p.id = pe.project_id 
        LEFT JOIN users u ON pe.user_id = u.id 
        GROUP BY p.id 
        ORDER BY p.created_at DESC
    ");
    $projectsStmt->execute();
    $ocp_endpoint['projects'] = $projectsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $ocp_endpoint['error_message'] = 'Error fetching projects: ' . $e->getMessage();
}

// --- the engineers the form can pick from ------------------------------------
try {
    $engineersStmt = $pdo->prepare("
        SELECT id, firstname, middlename, lastname, suffix 
        FROM users 
        WHERE department = 'Engineering' AND status = 'active' 
        ORDER BY firstname, lastname
    ");
    $engineersStmt->execute();
    $ocp_endpoint['engineers'] = $engineersStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $ocp_endpoint['error_message'] = 'Error fetching engineers: ' . $e->getMessage();
}

return $ocp_endpoint;
