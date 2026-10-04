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
require_once 'config/db_config.php';
// All of this page's actions live in one file: creating a backup, and setting or
// removing the automatic-backup schedule. Its helpers live there too, because
// the due-backup check is what performs a backup. The forms post back to this
// page, so it is pulled in before anything is read or rendered.
if (!defined('OCP_BACKUP_SQL_ACTIONS_RAN')) {
    require __DIR__ . '/actions/backup_sql-actions.php';
}

// All of this page's fetching lives in one file: it returns the variables the
// markup below needs, which are unpacked into this scope.
$ocp_endpoint = require __DIR__ . '/api/backup_sql-endpoint.php';
foreach ($ocp_endpoint as $ocp_key => $ocp_value) {
    ${$ocp_key} = $ocp_value;
}
unset($ocp_endpoint, $ocp_key, $ocp_value);
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>Database Backup - SKYLINE</title>
        <link href="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/style.min.css" rel="stylesheet" />
        <link href="assets/css/app.css" rel="stylesheet" />
        <link href="assets/css/app.build.css" rel="stylesheet" />
        <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
    </head>
    <body class="sb-nav-fixed">
        <?php include 'includes/top_bar.php';?>
        <div id="layoutSidenav">
            <?php include 'includes/side_menu.php';?>
            <div id="layoutSidenav_content" class="sb-content">
                <main>
                    <div class="w-full px-6">
                        <div class="mb-6">
                            <h1 class="page-title">Database Backup</h1>
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                <li class="breadcrumb-item active">Database Backup</li>
                            </ol>
                        </div>
                        
                        <div class="card mb-6">
                            <div class="card-header">
                                <i class="fas fa-database mr-1"></i>
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
                                        <i class="fas fa-download mr-1"></i> Download Database Backup
                                    </button>
                                </form>
                            </div>
                        </div>
                        
                        <div class="card mb-6">
                            <div class="card-header">
                                <i class="fas fa-calendar mr-1"></i>
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
                                    <div class="alert alert-info mb-4">
                                        <h6>Current Schedule:</h6>
                                        <p>Frequency: <?php echo ucfirst($current_schedule['frequency']); ?><br>
                                        Time: <?php echo $current_schedule['time']; ?><br>
                                        Last Run: <?php echo $current_schedule['last_run'] ? $current_schedule['last_run'] : 'Never'; ?><br>
                                        Next Run: <?php echo $current_schedule['next_run']; ?></p>
                                    </div>
                                    
                                    <form method="post" class="mb-4">
                                        <button type="submit" name="remove_schedule" class="btn btn-danger" onclick="return confirm('Are you sure you want to remove the auto backup schedule?')">
                                            <i class="fas fa-trash mr-1"></i> Remove Auto Backup Schedule
                                        </button>
                                    </form>
                                <?php endif; ?>
                                
                                <form method="post">
                                    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2 mb-4">
                                        <div class="min-w-0">
                                            <label for="backup_frequency" class="form-label">Frequency</label>
                                            <select class="form-select" id="backup_frequency" name="backup_frequency" required>
                                                <option value="daily" <?php echo ($current_schedule && $current_schedule['frequency'] == 'daily') ? 'selected' : ''; ?>>Daily</option>
                                                <option value="weekly" <?php echo ($current_schedule && $current_schedule['frequency'] == 'weekly') ? 'selected' : ''; ?>>Weekly</option>
                                                <option value="monthly" <?php echo ($current_schedule && $current_schedule['frequency'] == 'monthly') ? 'selected' : ''; ?>>Monthly</option>
                                            </select>
                                        </div>
                                        <div class="min-w-0">
                                            <label for="backup_time" class="form-label">Time (HH:MM)</label>
                                            <input type="time" class="form-control" id="backup_time" name="backup_time" value="<?php echo $current_schedule ? $current_schedule['time'] : '02:00'; ?>" required>
                                        </div>
                                    </div>
                                    <button type="submit" name="schedule_backup" class="btn btn-success">
                                        <i class="fas fa-calendar-check mr-1"></i> <?php echo $current_schedule ? 'Update' : 'Schedule'; ?> Auto Backup
                                    </button>
                                </form>
                                
                                <div class="mt-6">
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
        <script src="<?php echo ocp_asset('assets/js/app.js'); ?>"></script>
        <script src="<?php echo ocp_asset('assets/js/backup_sql.js'); ?>"></script>
    </body>
</html>