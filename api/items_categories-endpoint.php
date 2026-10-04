<?php
/**
 * api/items_categories-endpoint.php
 *
 * Every read for items_categories.php lives in this one file: the table is made
 * sure to exist, then the category listing is read.
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
 *   categories   array  every category, newest first
 */

$ocp_endpoint = [
    'categories' => [],
];

// Standalone guard: see the note above.
if (!isset($pdo)) {
    return $ocp_endpoint;
}

// The page has to work on a fresh database, so the table is created on demand.
$createTableSQL = "CREATE TABLE IF NOT EXISTS items_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

try {
    $pdo->exec($createTableSQL);
} catch (PDOException $e) {
    die("Error creating table: " . $e->getMessage());
}

try {
    $categoriesStmt = $pdo->prepare("SELECT * FROM items_categories ORDER BY created_at DESC");
    $categoriesStmt->execute();
    $ocp_endpoint['categories'] = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['sweetalert'] = [
        'title' => 'Database Error!',
        'text' => 'Error fetching categories: ' . $e->getMessage(),
        'icon' => 'error',
    ];
}

return $ocp_endpoint;
