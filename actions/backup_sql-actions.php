<?php
/**
 * actions/backup_sql-actions.php
 *
 * Every action for backup_sql.php lives in this one file: creating a backup,
 * setting and removing the automatic-backup schedule, and the three helpers those
 * need.
 *
 * The page pulls this file in at the top, so it runs in the page's scope. The
 * helper functions and the "should an automatic backup run now?" check live here
 * too, because they belong to the action rather than to the rendering: the check
 * is what actually performs a due backup.
 *
 * The blocks below are lifted verbatim from backup_sql.php: the queries, the file
 * handling and the messages are unchanged.
 */

if (defined('OCP_BACKUP_SQL_ACTIONS_RAN')) {
    return;
}
define('OCP_BACKUP_SQL_ACTIONS_RAN', true);

$ocp_is_post = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
$ocp_is_action = $ocp_is_post && (isset($_POST['backup']) || isset($_POST['schedule_backup']) || isset($_POST['remove_schedule']));
// The three handlers, which only run when one of their buttons was pressed.
// ---------------------------------------------------------------- backup
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

// ---------------------------------------------------------------- schedule
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

// ---------------------------------------------------------------- remove_schedule
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

// The schedule helpers. calculateNextRun() is used by the handlers above, and
// checkAutoBackup() runs on every page load: a backup that has come due is an
// action, so it is performed here rather than while the page renders.
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
