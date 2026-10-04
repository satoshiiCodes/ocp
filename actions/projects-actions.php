<?php
/**
 * actions/projects-actions.php
 *
 * Every action for projects.php lives in this one file: making sure the two
 * tables exist, and adding a project together with its engineers.
 *
 * The page pulls this file in at the top, so it runs in the page's scope. On a
 * validation error it leaves $error_message set and $_POST intact, and the page's
 * markup below reads $_POST through getPostValue() to repopulate the form. On
 * success it stores the message in the session and redirects, so a refresh
 * cannot resubmit the form.
 */

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !isset($_POST['project_name'])) {
    return;
}
if (defined('OCP_PROJECTS_ACTIONS_RAN')) {
    return;
}
define('OCP_PROJECTS_ACTIONS_RAN', true);

// ------------------------------------------------------- the tables must exist
$createTableSQL = "CREATE TABLE IF NOT EXISTS projects (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    project_name VARCHAR(255) NOT NULL,
    project_code VARCHAR(50) NOT NULL,
    address TEXT,
    description TEXT,
    start_date DATE,
    end_date DATE,
    status ENUM('planning', 'active', 'completed', 'on-hold') DEFAULT 'planning',
    threshold_amount DECIMAL(15,2) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

$createEngineersTableSQL = "CREATE TABLE IF NOT EXISTS project_engineers (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    project_id INT(11) NOT NULL,
    user_id INT(11) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";

try {
    $pdo->exec($createTableSQL);
} catch (PDOException $e) {
    die("Error creating table: " . $e->getMessage());
}

try {
    $pdo->exec($createEngineersTableSQL);
} catch (PDOException $e) {
    die("Error creating engineers table: " . $e->getMessage());
}

// --------------------------------------------------------------- add a project
$project_name = trim($_POST['project_name'] ?? '');
$project_code = trim($_POST['project_code'] ?? '');
$address = trim($_POST['address'] ?? '');
$description = trim($_POST['description'] ?? '');
$start_date = trim($_POST['start_date'] ?? '');
$end_date = trim($_POST['end_date'] ?? '');
$status = trim($_POST['status'] ?? 'planning');
$threshold_amount = isset($_POST['threshold_amount']) && $_POST['threshold_amount'] !== ''
    ? floatval(str_replace(',', '', $_POST['threshold_amount']))
    : null;

// The engineers chosen in the form
$selected_engineers = [];
if (isset($_POST['engineers']) && is_array($_POST['engineers'])) {
    foreach ($_POST['engineers'] as $engineer_id) {
        if (!empty($engineer_id)) {
            $selected_engineers[] = (int) $engineer_id;
        }
    }
}

if ($project_name === '') {
    $error_message = 'Project name is required.';
    return;
}
if ($project_code === '') {
    $error_message = 'Project code is required.';
    return;
}
if (count($selected_engineers) === 0) {
    $error_message = 'At least one engineer is required.';
    return;
}

try {
    $pdo->beginTransaction();

    $insertStmt = $pdo->prepare("INSERT INTO projects (project_name, project_code, address, description, start_date, end_date, status, threshold_amount) 
                               VALUES (:project_name, :project_code, :address, :description, :start_date, :end_date, :status, :threshold_amount)");
    $insertStmt->bindParam(':project_name', $project_name);
    $insertStmt->bindParam(':project_code', $project_code);
    $insertStmt->bindParam(':address', $address);
    $insertStmt->bindParam(':description', $description);
    $insertStmt->bindParam(':start_date', $start_date);
    $insertStmt->bindParam(':end_date', $end_date);
    $insertStmt->bindParam(':status', $status);
    $insertStmt->bindParam(':threshold_amount', $threshold_amount, PDO::PARAM_STR);

    if (!$insertStmt->execute()) {
        $pdo->rollBack();
        $error_message = 'Error adding project. Please try again.';
        return;
    }

    $project_id = $pdo->lastInsertId();
    $engineerStmt = $pdo->prepare("INSERT INTO project_engineers (project_id, user_id) VALUES (:project_id, :user_id)");

    foreach ($selected_engineers as $engineer_user_id) {
        $engineerStmt->bindParam(':project_id', $project_id);
        $engineerStmt->bindParam(':user_id', $engineer_user_id);
        $engineerStmt->execute();
    }

    $pdo->commit();

    // Show it once, after the redirect
    $_SESSION['success_message'] = 'Project added successfully!';
    header('Location: projects.php');
    exit();
} catch (PDOException $e) {
    $pdo->rollBack();
    $error_message = 'Database error: ' . $e->getMessage();
}
