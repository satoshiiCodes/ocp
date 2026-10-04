<?php
session_start();
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Set timezone to Philippines
date_default_timezone_set('Asia/Manila');

// Database connection
require_once 'includes/db_config.php';

// Get user details
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT firstname, middlename, lastname, suffix FROM users WHERE id = :id");
$stmt->bindParam(':id', $user_id);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Format the display name
$display_name = $user['firstname'];

if (!empty($user['middlename'])) {
    $display_name .= ' ' . substr($user['middlename'], 0, 1) . '.';
}

$display_name .= ' ' . $user['lastname'];

if (!empty($user['suffix'])) {
    $display_name .= ' ' . $user['suffix'];
}

// Handle backup request
if (isset($_POST['backup'])) {
    // Create backup using PHP instead of mysqldump
    try {
        // Get all table names
        $tables = array();
        $result = $pdo->query("SHOW TABLES");
        while ($row = $result->fetch(PDO::FETCH_NUM)) {
            $tables[] = $row[0];
        }
        
        if (count($tables) === 0) {
            $error = "No tables found in database.";
        } else {
            // Create SQL backup content
            $sql = "-- SKYLINE Database Backup\n";
            $sql .= "-- Generated: " . date('Y-m-d H:i:s') . " (Philippine Time)\n";
            $sql .= "-- Database: " . $dbname . "\n\n";
            $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
            
            // Iterate through tables
            foreach ($tables as $table) {
                // Add DROP TABLE statement
                $sql .= "DROP TABLE IF EXISTS `$table`;\n";
                
                // Get CREATE TABLE statement
                $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM);
                $sql .= $create[1] . ";\n\n";
                
                // Get table data
                $data = $pdo->query("SELECT * FROM `$table`");
                $rowCount = $data->rowCount();
                
                if ($rowCount > 0) {
                    $sql .= "-- Dumping data for table `$table`\n";
                    
                    // Get column names
                    $columns = array();
                    $cols = $pdo->query("SHOW COLUMNS FROM `$table`");
                    while ($col = $cols->fetch(PDO::FETCH_ASSOC)) {
                        $columns[] = "`" . $col['Field'] . "`";
                    }
                    $colList = implode(', ', $columns);
                    
                    // Add INSERT statements
                    while ($row = $data->fetch(PDO::FETCH_ASSOC)) {
                        $values = array();
                        foreach ($row as $value) {
                            if ($value === null) {
                                $values[] = "NULL";
                            } else {
                                $values[] = $pdo->quote($value);
                            }
                        }
                        $sql .= "INSERT INTO `$table` ($colList) VALUES (" . implode(', ', $values) . ");\n";
                    }
                    $sql .= "\n";
                }
            }
            
            $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
            
            // Set headers for download
            $backup_file_name = 'db_skyline_backup_' . date('Y-m-d_H-i-s') . '.sql';
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $backup_file_name . '"');
            echo $sql;
            exit();
        }
    } catch (Exception $e) {
        $error = "Backup failed: " . $e->getMessage();
    }
}

// Handle auto backup schedule
if (isset($_POST['schedule_backup'])) {
    $backup_time = $_POST['backup_time'];
    $backup_frequency = $_POST['backup_frequency'];
    
    // Validate time format
    if (!preg_match("/^(0[0-9]|1[0-9]|2[0-3]):[0-5][0-9]$/", $backup_time)) {
        $schedule_error = "Invalid time format. Please use HH:MM format.";
    } else {
        // Save schedule to database or file
        $schedule_data = [
            'time' => $backup_time,
            'frequency' => $backup_frequency,
            'last_run' => null,
            'next_run' => calculateNextRun($backup_time, $backup_frequency)
        ];
        
        // Save to file (you might want to use a database instead)
        file_put_contents('backup_schedule.json', json_encode($schedule_data));
        $schedule_success = "Auto backup scheduled successfully!";
    }
}

// Handle remove auto backup schedule
if (isset($_POST['remove_schedule'])) {
    if (file_exists('backup_schedule.json')) {
        if (unlink('backup_schedule.json')) {
            $schedule_success = "Auto backup schedule removed successfully!";
            $current_schedule = null;
        } else {
            $schedule_error = "Failed to remove auto backup schedule.";
        }
    } else {
        $schedule_error = "No auto backup schedule found.";
    }
}

// Function to calculate next run time
function calculateNextRun($time, $frequency) {
    $now = time();
    $today = date('Y-m-d');
    
    switch ($frequency) {
        case 'daily':
            $next_run = strtotime($today . ' ' . $time);
            if ($next_run <= $now) {
                $next_run = strtotime('+1 day', $next_run);
            }
            break;
            
        case 'weekly':
            $next_run = strtotime($today . ' ' . $time);
            if ($next_run <= $now) {
                $next_run = strtotime('+1 week', $next_run);
            } else {
                $next_run = strtotime('next week ' . $time);
            }
            break;
            
        case 'monthly':
            $next_run = strtotime($today . ' ' . $time);
            if ($next_run <= $now) {
                $next_run = strtotime('+1 month', $next_run);
            } else {
                $next_run = strtotime('first day of next month ' . $time);
            }
            break;
            
        default:
            $next_run = strtotime($today . ' ' . $time);
            if ($next_run <= $now) {
                $next_run = strtotime('+1 day', $next_run);
            }
    }
    
    return date('Y-m-d H:i:s', $next_run);
}

// Check if auto backup should run now
function checkAutoBackup() {
    if (file_exists('backup_schedule.json')) {
        $schedule = json_decode(file_get_contents('backup_schedule.json'), true);
        $now = time();
        
        if ($schedule['next_run'] && strtotime($schedule['next_run']) <= $now) {
            // Execute backup
            performAutoBackup();
            
            // Update schedule
            $schedule['last_run'] = date('Y-m-d H:i:s');
            $schedule['next_run'] = calculateNextRun($schedule['time'], $schedule['frequency']);
            file_put_contents('backup_schedule.json', json_encode($schedule));
        }
    }
}

// Function to perform auto backup
function performAutoBackup() {
    global $pdo, $dbname;
    
    try {
        // Get all table names
        $tables = array();
        $result = $pdo->query("SHOW TABLES");
        while ($row = $result->fetch(PDO::FETCH_NUM)) {
            $tables[] = $row[0];
        }
        
        if (count($tables) > 0) {
            // Create SQL backup content
            $sql = "-- SKYLINE Database Backup (Auto)\n";
            $sql .= "-- Generated: " . date('Y-m-d H:i:s') . " (Philippine Time)\n";
            $sql .= "-- Database: " . $dbname . "\n\n";
            $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
            
            // Iterate through tables
            foreach ($tables as $table) {
                // Add DROP TABLE statement
                $sql .= "DROP TABLE IF EXISTS `$table`;\n";
                
                // Get CREATE TABLE statement
                $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM);
                $sql .= $create[1] . ";\n\n";
                
                // Get table data
                $data = $pdo->query("SELECT * FROM `$table`");
                $rowCount = $data->rowCount();
                
                if ($rowCount > 0) {
                    $sql .= "-- Dumping data for table `$table`\n";
                    
                    // Get column names
                    $columns = array();
                    $cols = $pdo->query("SHOW COLUMNS FROM `$table`");
                    while ($col = $cols->fetch(PDO::FETCH_ASSOC)) {
                        $columns[] = "`" . $col['Field'] . "`";
                    }
                    $colList = implode(', ', $columns);
                    
                    // Add INSERT statements
                    while ($row = $data->fetch(PDO::FETCH_ASSOC)) {
                        $values = array();
                        foreach ($row as $value) {
                            if ($value === null) {
                                $values[] = "NULL";
                            } else {
                                $values[] = $pdo->quote($value);
                            }
                        }
                        $sql .= "INSERT INTO `$table` ($colList) VALUES (" . implode(', ', $values) . ");\n";
                    }
                    $sql .= "\n";
                }
            }
            
            $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
            
            // Save to backups directory
            $backup_dir = 'backups/';
            if (!file_exists($backup_dir)) {
                mkdir($backup_dir, 0777, true);
            }
            
            $backup_file_name = $backup_dir . 'auto_backup_' . date('Y-m-d_H-i-s') . '.sql';
            file_put_contents($backup_file_name, $sql);
            
            // Log the backup
            $log = date('Y-m-d H:i:s') . " - Auto backup created: $backup_file_name\n";
            file_put_contents($backup_dir . 'backup_log.txt', $log, FILE_APPEND);
        }
    } catch (Exception $e) {
        // Log error
        $error_log = date('Y-m-d H:i:s') . " - Auto backup failed: " . $e->getMessage() . "\n";
        file_put_contents($backup_dir . 'backup_log.txt', $error_log, FILE_APPEND);
    }
}

// Check for auto backup on page load
checkAutoBackup();

// Get current schedule if exists
$current_schedule = null;
if (file_exists('backup_schedule.json')) {
    $current_schedule = json_decode(file_get_contents('backup_schedule.json'), true);
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>Database Backup - SKYLINE</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" />
        <link href="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/style.min.css" rel="stylesheet" />
        <link href="css/styles.css" rel="stylesheet" />
        <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
    </head>
    <body class="sb-nav-fixed">
        <?php include 'includes/top_bar.php';?>
        <div id="layoutSidenav">
            <?php include 'includes/side_menu.php';?>
            <div id="layoutSidenav_content">
                <main>
                    <div class="container-fluid px-4">
                        <h1 class="mt-4">Database Backup</h1>
                        <ol class="breadcrumb mb-4">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Database Backup</li>
                        </ol>
                        
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="fas fa-database me-1"></i>
                                Backup Database
                            </div>
                            <div class="card-body">
                                <?php if (isset($error)): ?>
                                    <div class="alert alert-danger"><?php echo $error; ?></div>
                                <?php endif; ?>
                                
                                <div class="alert alert-info">
                                    <h5><i class="fas fa-info-circle"></i> Backup Information</h5>
                                    <p>Click the button below to create a backup of your database. This will generate a complete SQL dump of your <strong>db_skyline</strong> database that you can download and use to restore your data if needed.</p>
                                    <p class="mb-0"><strong>Note:</strong> The backup process may take a few moments depending on the size of your database.</p>
                                </div>
                                
                                <form method="post">
                                    <button type="submit" name="backup" class="btn btn-primary">
                                        <i class="fas fa-download me-1"></i> Download Database Backup
                                    </button>
                                </form>
                            </div>
                        </div>
                        
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="fas fa-calendar me-1"></i>
                                Auto Backup Schedule
                            </div>
                            <div class="card-body">
                                <?php if (isset($schedule_error)): ?>
                                    <div class="alert alert-danger"><?php echo $schedule_error; ?></div>
                                <?php endif; ?>
                                
                                <?php if (isset($schedule_success)): ?>
                                    <div class="alert alert-success"><?php echo $schedule_success; ?></div>
                                <?php endif; ?>
                                
                                <?php if ($current_schedule): ?>
                                    <div class="alert alert-info mb-3">
                                        <h6>Current Schedule:</h6>
                                        <p>Frequency: <?php echo ucfirst($current_schedule['frequency']); ?><br>
                                        Time: <?php echo $current_schedule['time']; ?><br>
                                        Last Run: <?php echo $current_schedule['last_run'] ? $current_schedule['last_run'] : 'Never'; ?><br>
                                        Next Run: <?php echo $current_schedule['next_run']; ?></p>
                                    </div>
                                    
                                    <form method="post" class="mb-3">
                                        <button type="submit" name="remove_schedule" class="btn btn-danger" onclick="return confirm('Are you sure you want to remove the auto backup schedule?')">
                                            <i class="fas fa-trash me-1"></i> Remove Auto Backup Schedule
                                        </button>
                                    </form>
                                <?php endif; ?>
                                
                                <form method="post">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="backup_frequency" class="form-label">Frequency</label>
                                            <select class="form-select" id="backup_frequency" name="backup_frequency" required>
                                                <option value="daily" <?php echo ($current_schedule && $current_schedule['frequency'] == 'daily') ? 'selected' : ''; ?>>Daily</option>
                                                <option value="weekly" <?php echo ($current_schedule && $current_schedule['frequency'] == 'weekly') ? 'selected' : ''; ?>>Weekly</option>
                                                <option value="monthly" <?php echo ($current_schedule && $current_schedule['frequency'] == 'monthly') ? 'selected' : ''; ?>>Monthly</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="backup_time" class="form-label">Time (HH:MM)</label>
                                            <input type="time" class="form-control" id="backup_time" name="backup_time" value="<?php echo $current_schedule ? $current_schedule['time'] : '02:00'; ?>" required>
                                        </div>
                                    </div>
                                    <button type="submit" name="schedule_backup" class="btn btn-success">
                                        <i class="fas fa-calendar-check me-1"></i> <?php echo $current_schedule ? 'Update' : 'Schedule'; ?> Auto Backup
                                    </button>
                                </form>
                                
                                <div class="mt-4">
                                    <h6>Backup Location:</h6>
                                    <p>Auto backups are saved in the <code>backups/</code> directory.</p>
                                    
                                    <?php
                                    $backup_dir = 'backups/';
                                    if (file_exists($backup_dir)) {
                                        $backups = glob($backup_dir . 'auto_backup_*.sql');
                                        if (count($backups) > 0) {
                                            echo '<h6>Recent Auto Backups:</h6>';
                                            echo '<ul>';
                                            $count = 0;
                                            foreach (array_reverse($backups) as $backup) {
                                                if ($count++ >= 5) break; // Show only last 5
                                                echo '<li>' . basename($backup) . ' (' . round(filesize($backup) / 1024, 2) . ' KB)</li>';
                                            }
                                            echo '</ul>';
                                        }
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </main>
                <?php include 'includes/footer.php';?>
            </div>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
        <script>
            // Logout function
            document.getElementById('logoutLink').addEventListener('click', function(e) {
                e.preventDefault();
                if (confirm('Are you sure you want to logout?')) {
                    window.location.href = 'action/logout.php';
                }
            });
            
            // Simple sidebar toggle functionality
            document.getElementById('sidebarToggle').addEventListener('click', function() {
                document.body.classList.toggle('sb-sidenav-toggled');
            });
        </script>
    </body>
</html>