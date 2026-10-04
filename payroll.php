<?php
session_start();
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Database connection
require_once 'config/db_config.php';

require_once __DIR__ . '/includes/page_data.php';
// Load PhpSpreadsheet library
require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

// The helper functions this page and its actions file both use: the overtime and
// attendance calculations, the CSV/Excel parsers, and the routine that writes
// parsed attendance to the database. Loaded here, before the actions run, because
// the POST handlers call them.
require_once __DIR__ . '/includes/payroll-functions.php';

// Get user details. This stays in the page because the side menu below prints the
// display name, so it is needed for the render and not just for an action.
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

// Initialize variables
$message = '';
$message_type = '';
$uploaded_data = [];
$selected_date_from = '';
$selected_date_to = '';
$selected_department = '';

// Process form submission
// All of this page's actions live in one file: the filters, deleting, editing and
// adding an attendance record, and importing a file. Every branch re-renders, so
// the messages and the chosen filters are left in scope for the markup below.
if (!defined('OCP_PAYROLL_ACTIONS_RAN')) {
    require __DIR__ . '/actions/payroll-actions.php';
}

// All of this page's fetching lives in one file: it returns the variables the
// markup below needs, which are unpacked into this scope. attendance_error is only
// taken when the endpoint set one, and the filters it reads come from the actions
// file above.
$ocp_endpoint = require __DIR__ . '/api/payroll-endpoint.php';
foreach ($ocp_endpoint as $ocp_key => $ocp_value) {
    if ($ocp_key === 'attendance_error' && empty($ocp_value)) {
        continue;
    }
    ${$ocp_key} = $ocp_value;
}
unset($ocp_endpoint, $ocp_key, $ocp_value);
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>Payroll - OCP Construction</title>
        <link rel="icon" type="image/png" href="assets/images/logo/OCP.png">
        <link href="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/style.min.css" rel="stylesheet" />
        <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
        <!-- SweetAlert2 CSS and JS -->
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <link href="assets/css/app.css" rel="stylesheet" />
        <link href="assets/css/app.build.css" rel="stylesheet" />
    </head>
    <body class="sb-nav-fixed">
        <?php include 'includes/top_bar.php';?>
        <div id="layoutSidenav">
            <?php include 'includes/side_menu.php';?>
            <div id="layoutSidenav_content" class="sb-content">
                <main>
                    <div class="w-full px-6">
                        <div class="mb-6">
                            <h1 class="page-title">Payroll</h1>
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                <li class="breadcrumb-item active">Payroll</li>
                            </ol>
                        </div>
                        
                        <div class="grid grid-cols-1 gap-6">
                            <div class="min-w-0">
                                <!-- Filter Records Form -->
                                <div class="card mb-6">
                                    <div class="card-header">
                                        <i class="fas fa-filter mr-1"></i>
                                        Filter Records
                                    </div>
                                    <div class="card-body">
                                        <form method="POST" class="grid grid-cols-1 gap-6 xl:grid-cols-2 items-end" id="filterForm">
                                            <div class="min-w-0">
                                                <label for="date_from" class="form-label">From Date</label>
                                                <input type="date" class="form-control" id="date_from" name="date_from" 
                                                       value="<?php echo $selected_date_from; ?>">
                                            </div>
                                            <div class="min-w-0">
                                                <label for="date_to" class="form-label">To Date</label>
                                                <input type="date" class="form-control" id="date_to" name="date_to" 
                                                       value="<?php echo $selected_date_to; ?>">
                                            </div>
                                            <div class="min-w-0">
                                                <label for="department_filter" class="form-label">Department</label>
                                                <select class="form-select" id="department_filter" name="department_filter">
                                                    <option value="">All Departments</option>
                                                    <?php foreach ($departments as $dept): ?>
                                                        <option value="<?php echo htmlspecialchars($dept['department']); ?>" 
                                                            <?php echo $selected_department == $dept['department'] ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($dept['department']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="min-w-0 flex gap-2">
                                                <button type="submit" name="apply_filter" class="btn btn-primary grow">
                                                    <i class="fas fa-check"></i> Apply
                                                </button>
                                                <button type="submit" name="clear_filter" class="btn btn-secondary grow">
                                                    <i class="fas fa-times"></i> Clear
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                                
                                <!-- Attendance Records Table -->
                                <?php if (!empty($attendance_records)): ?>
                                <div class="card mb-6">
                                    <div class="card-header flex justify-between items-center">
                                        <div>
                                            <i class="fas fa-clock mr-1"></i>
                                            Attendance Records (<?php echo count($attendance_records); ?> records)
                                            <?php if (!empty($selected_department)): ?>
                                                <span class="badge badge-info ml-2">Department: <?php echo htmlspecialchars($selected_department); ?></span>
                                            <?php endif; ?>
                                            <?php if (empty($selected_date_from) || empty($selected_date_to)): ?>
                                                <span class="badge badge-warning ml-2">All Dates</span>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <a href="generate_payslip_pdf.php?<?php 
                                                echo http_build_query([
                                                    'date_from' => $selected_date_from,
                                                    'date_to' => $selected_date_to,
                                                    'department' => $selected_department
                                                ]); 
                                            ?>" target="_blank" class="btn btn-warning btn-sm">
                                                <i class="fas fa-file-pdf mr-1"></i> Generate Payslip PDF
                                            </a>

                                            <a href="generate_payroll_pdf.php?<?php 
                                                echo http_build_query([
                                                    'date_from' => $selected_date_from,
                                                    'date_to' => $selected_date_to,
                                                    'department' => $selected_department
                                                ]); 
                                            ?>" target="_blank" class="btn btn-success btn-sm">
                                                <i class="fas fa-file-pdf mr-1"></i> Generate Payroll PDF
                                            </a>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-striped table-bordered" id="attendanceRecordsTable">
                                                <thead>
                                                    <tr>
                                                        <th>Date</th>
                                                        <th>Employee</th>
                                                        <th>Department</th>
                                                        <th>Check In</th>
                                                        <th>Break Out</th>
                                                        <th>Break In</th>
                                                        <th>Check Out</th>
                                                        <th>Late (min)</th>
                                                        <th>OT (min)</th>
                                                        <th>Status</th>
                                                        <th>Remarks</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($attendance_records as $record): ?>
                                                        <tr>
                                                            <td><?php echo date('m-d-Y', strtotime($record['attendance_date'])); ?></td>
                                                            <td>
                                                                <?php echo htmlspecialchars($record['employee_name']); ?>
                                                                <br><small class="text-muted">ID: <?php echo htmlspecialchars($record['emp_code']); ?></small>
                                                            </td>
                                                            <td><?php echo htmlspecialchars($record['department']); ?></td>
                                                            <td class="text-success-600! font-bold"><?php echo $record['check_in'] ? formatTimeTo12Hour($record['check_in']) : '--:--'; ?></td>
                                                            <td><?php echo $record['break_out'] ? formatTimeTo12Hour($record['break_out']) : '--:--'; ?></td>
                                                            <td><?php echo $record['break_in'] ? formatTimeTo12Hour($record['break_in']) : '--:--'; ?></td>
                                                            <td class="text-danger-600! font-bold"><?php echo $record['check_out'] ? formatTimeTo12Hour($record['check_out']) : '--:--'; ?></td>
                                                            <td class="<?php echo $record['late_time'] > 0 ? 'text-danger-600! font-bold' : ''; ?>">
                                                                <?php echo $record['late_time'] > 0 ? $record['late_time'] : '-'; ?>
                                                            </td>
                                                            <td class="<?php echo $record['over_time'] > 0 ? 'text-success-600! font-bold' : ''; ?>">
                                                                <?php echo $record['over_time'] > 0 ? $record['over_time'] : '-'; ?>
                                                            </td>
                                                            <td class="text-center">
                                                                <?php
                                                                $status = $record['status'] ?? 'Present';
                                                                $status_class = 'badge-success';
                                                                if ($status == 'Half Day') {
                                                                    $status_class = 'badge-warning';
                                                                } elseif ($status == 'Absent') {
                                                                    $status_class = 'badge-danger';
                                                                }
                                                                ?>
                                                                <span class="badge <?php echo $status_class; ?>">
                                                                    <?php echo htmlspecialchars($status); ?>
                                                                </span>
                                                            </td>
                                                            <td><?php echo !empty($record['remarks']) ? htmlspecialchars($record['remarks']) : '-'; ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                                <?php elseif (!isset($attendance_error)): ?>
                                <div class="card mb-6">
                                    <div class="card-header flex justify-between items-center">
                                        <div>
                                            <i class="fas fa-clock mr-1"></i>
                                            Attendance Records
                                        </div>
                                        <div>
                                            <a href="generate_payroll_pdf.php?<?php 
                                                echo http_build_query([
                                                    'date_from' => $selected_date_from,
                                                    'date_to' => $selected_date_to,
                                                    'department' => $selected_department
                                                ]); 
                                            ?>" target="_blank" class="btn btn-success btn-sm" <?php echo empty($attendance_records) ? 'disabled' : ''; ?>>
                                                <i class="fas fa-file-pdf mr-1"></i> Generate Payroll PDF
                                            </a>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="alert alert-info mb-0" id="noRecordsAlert">
                                            <i class="fas fa-info-circle mr-2"></i>
                                            No attendance records found.
                                            <?php if (!empty($selected_department) || !empty($selected_date_from) || !empty($selected_date_to)): ?>
                                                <br>Try adjusting your filters to see more results.
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                        </div>
                    </div>
                </main>
                <?php include 'includes/footer.php';?>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>
        <?php
        /* Data island consumed by assets/js/payroll.js. */
        $__ocp_data = [];
        /* messageType = $message_type [guarded] */
        if ($message) {
            $__ocp_data["messageType"] = $message_type;
        }
        /* messageType2 = $message_type == 'success' ? 'Success!' : 'Error!' [guarded] */
        if ($message) {
            $__ocp_data["messageType2"] = $message_type == 'success' ? 'Success!' : 'Error!';
        }
        /* message = $message [guarded] */
        if ($message) {
            $__ocp_data["message"] = $message;
        }
        /* messageType3 = $message_type == 'success' ? 3000 : 5000 [guarded] */
        if ($message) {
            $__ocp_data["messageType3"] = $message_type == 'success' ? 3000 : 5000;
        }
        /* attendanceError = $attendance_error [guarded] */
        if (isset($attendance_error)) {
            $__ocp_data["attendanceError"] = $attendance_error;
        }
        /* Flags for the script. It is a separate request, so it cannot test this
         * page's variables itself; these answer for it. Each is false on an ordinary
         * load, so nothing is shown unless there is something to show. */
        $__ocp_data["hasMessage"] = (!empty($message));
        $__ocp_data["hasAttendanceError"] = (isset($attendance_error));
        ocp_page_data("payroll", $__ocp_data);
        unset($__ocp_data);
        ?>
        <script src="<?php echo ocp_asset('assets/js/app.js'); ?>"></script>
        <script src="assets/js/payroll.js.php"></script>
    </body>
</html>
