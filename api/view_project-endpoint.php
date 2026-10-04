<?php
/**
 * api/view_project-endpoint.php
 *
 * Every read for view_project.php lives in this one file: the project and its
 * engineers, its stock movements, subcon materials, eligible and assigned workers,
 * the vehicles and equipment available to rent, its rentals, and the signed-in
 * user's display name.
 *
 * The page pulls this in instead of querying the database itself, so all of the
 * page's fetching is in one place. It runs in the page's scope and returns an
 * array of the variables the markup needs; the page unpacks that array. Nothing
 * is printed here, so this file cannot disturb the page's output.
 *
 * Which project to read is decided here, from the posted id or the id remembered in
 * the session - the same rule the page used.
 *
 * Returns
 *   project            array  the project, with its engineers
 *   stock_movements    array  the project's stock movements, if the table exists
 *   stock_table_exists bool   whether that table exists at all
 *   subcon_materials   array  the project's subcon materials
 *   eligible_workers   array  the workers who could be added
 *   project_workers    array  the workers already on the project
 *   vehicles           array  the vehicles available to rent
 *   equipment          array  the equipment available to rent
 *   project_rentals    array  the project's rentals
 *   display_name       string the signed-in user's name, for the side menu
 */

$ocp_endpoint = [
    'project' => null,
    'stock_movements' => [],
    'stock_table_exists' => false,
    'subcon_materials' => [],
    'eligible_workers' => [],
    'project_workers' => [],
    'vehicles' => [],
    'equipment' => [],
    'project_rentals' => [],
    // The id the page's forms carry, so an action posted from this page knows which
    // project it is for.
    'project_id' => null,
];

// This endpoint owns the database connection: the page's connection line moved with
// the queries it served, and the endpoint runs before the page needs it. It also
// opens the session, which the "remembered project" check below reads.
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

// Which project to read. The actions file has normally already decided this; when
// the endpoint is reached on its own with nothing to go on, it answers with the
// empty result rather than redirecting, so a direct request stays harmless. The
// page still redirects, because its own actions file applies the same rule first.
if (!isset($project_id)) {
    if (!isset($_POST['id']) || empty($_POST['id'])) {
        if (!isset($_SESSION['current_project_id'])) {
            return $ocp_endpoint;
        }
        $project_id = $_SESSION['current_project_id'];
    } else {
        $project_id = $_POST['id'];
        $_SESSION['current_project_id'] = $project_id;
    }
}
// Hand the resolved id back, so the page's hidden fields carry it and an action
// posted from the page knows which project it acts on.
$ocp_endpoint['project_id'] = $project_id;

// Get project details with engineers
try {
    $projectStmt = $pdo->prepare("
        SELECT p.*, GROUP_CONCAT(CONCAT(u.firstname, ' ', u.lastname) SEPARATOR ', ') as engineers 
        FROM projects p 
        LEFT JOIN project_engineers pe ON p.id = pe.project_id 
        LEFT JOIN users u ON pe.user_id = u.id 
        WHERE p.id = :id
        GROUP BY p.id
    ");
    $projectStmt->bindParam(':id', $project_id);
    $projectStmt->execute();
    
    if ($projectStmt->rowCount() === 0) {
        header('Location: projects.php');
        exit();
    }
    
    $ocp_endpoint['project'] = $projectStmt->fetch(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Error fetching project: " . $e->getMessage());
}

// Check if stock_movements table exists and get related data
$ocp_endpoint['stock_movements'] = [];
$ocp_endpoint['stock_table_exists'] = false;

try {
    // Check if stock_movements table exists
    $checkTableStmt = $pdo->query("SHOW TABLES LIKE 'stock_movements'");
    if ($checkTableStmt->rowCount() > 0) {
        $ocp_endpoint['stock_table_exists'] = true;
        
        // Check if stock_movements has a project_id column
        $checkColumnStmt = $pdo->query("SHOW COLUMNS FROM stock_movements LIKE 'project_id'");
        if ($checkColumnStmt->rowCount() > 0) {
            // Get stock movements for this project with item names and codes
            $stockStmt = $pdo->prepare("
                SELECT sm.movement_date, sm.quantity, sm.unit_cost,
                       inames.item_name, inames.item_code
                FROM stock_movements sm 
                LEFT JOIN item_names inames ON sm.item_id = inames.id 
                WHERE sm.project_id = :project_id 
                ORDER BY sm.movement_date DESC
            ");
            $stockStmt->bindParam(':project_id', $project_id);
            $stockStmt->execute();
            $ocp_endpoint['stock_movements'] = $stockStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} catch(PDOException $e) {
    // If there's an error, we'll just not show the stock movements
    $ocp_endpoint['stock_table_exists'] = false;
}

// Get subcon materials data
$ocp_endpoint['subcon_materials'] = [];
try {
    // Check if stock_movements has a subcon_id column
    $checkColumnStmt = $pdo->query("SHOW COLUMNS FROM stock_movements LIKE 'subcon_id'");
    if ($checkColumnStmt->rowCount() > 0) {
        // Get subcon materials for this project
        $subconStmt = $pdo->prepare("
            SELECT sm.movement_date, sm.quantity, sm.unit_cost,
                   inames.item_name, inames.item_code, s.subcon_name
            FROM stock_movements sm 
            LEFT JOIN item_names inames ON sm.item_id = inames.id 
            LEFT JOIN subcons s ON sm.subcon_id = s.id 
            WHERE sm.project_id = :project_id AND sm.subcon_id IS NOT NULL
            ORDER BY sm.movement_date DESC
        ");
        $subconStmt->bindParam(':project_id', $project_id);
        $subconStmt->execute();
        $ocp_endpoint['subcon_materials'] = $subconStmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch(PDOException $e) {
    // If there's an error, we'll just not show the subcon materials
    $subcon_materials_error = "Error fetching subcon materials: " . $e->getMessage();
}

// Get eligible workers (Foreman, Skilled, Welder, Helper)
$ocp_endpoint['eligible_workers'] = [];
try {
    $workersStmt = $pdo->prepare("
        SELECT id, employee_id, firstname, lastname, position 
        FROM employee 
        WHERE position IN ('Foreman', 'Skilled', 'Welder', 'Helper') AND status = 'active'
        ORDER BY firstname, lastname
    ");
    $workersStmt->execute();
    $ocp_endpoint['eligible_workers'] = $workersStmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    // If there's an error, we'll just not show the workers dropdown
    $eligible_workers_error = "Error fetching workers: " . $e->getMessage();
}

// Get current project workers
$ocp_endpoint['project_workers'] = [];
try {
    $projectWorkersStmt = $pdo->prepare("
        SELECT e.id, e.employee_id, e.firstname, e.lastname, e.position 
        FROM project_workers pw 
        JOIN employee e ON pw.user_id = e.id 
        WHERE pw.project_id = :project_id
        ORDER BY e.firstname, e.lastname
    ");
    $projectWorkersStmt->bindParam(':project_id', $project_id);
    $projectWorkersStmt->execute();
    $ocp_endpoint['project_workers'] = $projectWorkersStmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    // If there's an error, we'll just not show the current workers
    $project_workers_error = "Error fetching project workers: " . $e->getMessage();
}

// Get vehicles for rental
$ocp_endpoint['vehicles'] = [];
try {
    $vehiclesStmt = $pdo->prepare("
        SELECT id, vehicle_name, plate_number, fuel_type 
        FROM vehicles 
        WHERE is_active = 1
        ORDER BY vehicle_name
    ");
    $vehiclesStmt->execute();
    $ocp_endpoint['vehicles'] = $vehiclesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $vehicles_error = "Error fetching vehicles: " . $e->getMessage();
}

// Get equipment for rental
$ocp_endpoint['equipment'] = [];
try {
    $equipmentStmt = $pdo->prepare("
        SELECT id, equipment_name, fuel_type 
        FROM equipment 
        WHERE is_active = 1
        ORDER BY equipment_name
    ");
    $equipmentStmt->execute();
    $ocp_endpoint['equipment'] = $equipmentStmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $equipment_error = "Error fetching equipment: " . $e->getMessage();
}

// Get project rentals
$ocp_endpoint['project_rentals'] = [];
try {
    $rentalsStmt = $pdo->prepare("
        SELECT pr.*, 
               COALESCE(v.vehicle_name, e.equipment_name) as item_name,
               COALESCE(v.plate_number, 'N/A') as plate_number
        FROM project_rentals pr
        LEFT JOIN vehicles v ON pr.vehicle_id = v.id
        LEFT JOIN equipment e ON pr.equipment_id = e.id
        WHERE pr.project_id = :project_id
        ORDER BY pr.start_date DESC
    ");
    $rentalsStmt->bindParam(':project_id', $project_id);
    $rentalsStmt->execute();
    $ocp_endpoint['project_rentals'] = $rentalsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $rentals_error = "Error fetching rentals: " . $e->getMessage();
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