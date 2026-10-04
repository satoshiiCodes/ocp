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

// The helper functions this page and its actions file both use: the attendance
// time parsing and status calculations, the CSV/Excel parsers, and the routine
// that writes parsed attendance to the database. Loaded here, before the actions
// run, because the POST handlers call them.
require_once __DIR__ . '/includes/upload_attendance-functions.php';

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
$selected_date_from = date('Y-m-d');
$selected_date_to = date('Y-m-d');

// All of this page's actions live in one file: the date filters, adding a record
// by hand, and importing a file. Every branch re-renders, so the messages and the
// chosen dates are left in scope for the markup below.
if (!defined('OCP_UPLOAD_ATTENDANCE_ACTIONS_RAN')) {
    require __DIR__ . '/actions/upload_attendance-actions.php';
}

// All of this page's fetching lives in one file: it returns the variables the
// markup below needs, which are unpacked into this scope. attendance_error is only
// taken when the endpoint set one, and the dates it reads come from the actions
// file above.
$ocp_endpoint = require __DIR__ . '/api/upload_attendance-endpoint.php';
foreach ($ocp_endpoint as $ocp_key => $ocp_value) {
    if ($ocp_key === 'attendance_error' && empty($ocp_value)) {
        continue;
    }
    ${$ocp_key} = $ocp_value;
}
unset($ocp_endpoint, $ocp_key, $ocp_value);
// Check for missing employees alert from session
$show_missing_alert = isset($_SESSION['missing_employees_alert']) && !empty($_SESSION['missing_employees_alert']);
$missing_employees_json = '';
if ($show_missing_alert) {
    $missing_employees_list = $_SESSION['missing_employees_alert'];
    $missing_success_count = $_SESSION['success_count'] ?? 0;
    $missing_count = $_SESSION['missing_count'] ?? 0;
    $missing_employees_json = json_encode([
        'employees' => $missing_employees_list,
        'success_count' => $missing_success_count,
        'missing_count' => $missing_count
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    unset($_SESSION['missing_employees_alert']);
    unset($_SESSION['success_count']);
    unset($_SESSION['missing_count']);
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>Upload Attendance - OCP Construction</title>
        <link rel="icon" type="image/png" href="assets/images/logo/OCP.png">
        <link href="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/style.min.css" rel="stylesheet" />
        <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
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
                            <h1 class="page-title">Upload Attendance</h1>
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                <li class="breadcrumb-item active">Upload Attendance</li>
                            </ol>
                        </div>
                        
                        <div class="grid grid-cols-1 gap-6">
                            <div class="min-w-0">
                                <div class="card mb-6">
                                    <div class="card-header"><i class="fas fa-upload mr-1"></i> Upload Attendance File</div>
                                    <div class="card-body">
                                        <form method="POST" enctype="multipart/form-data" id="attendanceForm">
                                            <div class="upload-area mb-4 border-2 border-dashed border-slate-300 rounded-lg p-8 text-center bg-slate-50 cursor-pointer transition hover:border-brand-500 hover:bg-slate-100" onclick="document.getElementById('attendance_file').click()">
                                                <i class="fas fa-cloud-upload-alt text-5xl text-slate-400"></i>
                                                <h5 class="text-lg font-semibold mt-4">Click to upload or drag and drop</h5>
                                                <p class="text-muted mb-0">Excel files (.xls, .xlsx) or CSV</p>
                                                <input type="file" id="attendance_file" name="attendance_file" accept=".xls,.xlsx,.csv" style="display: none;" required>
                                            </div>
                                            <div class="grid"><button type="submit" class="btn btn-primary"><i class="fas fa-upload mr-1"></i> Upload & Process</button></div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="min-w-0">
                                <div class="card mb-6">
                                    <div class="card-header"><i class="fas fa-calendar-alt mr-1"></i> View Attendance Records</div>
                                    <div class="card-body">
                                        <form method="POST" class="grid grid-cols-1 gap-6 xl:grid-cols-2" id="dateRangeForm">
                                            <div class="min-w-0">
                                                <label for="date_from" class="form-label">From Date</label>
                                                <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo $selected_date_from; ?>" required>
                                            </div>
                                            <div class="min-w-0">
                                                <label for="date_to" class="form-label">To Date</label>
                                                <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo $selected_date_to; ?>" required>
                                            </div>
                                            <div class="min-w-0 flex items-end">
                                                <button type="submit" name="select_date_range" class="btn btn-primary w-full"><i class="fas fa-search"></i> View</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                                
                                <?php if (!empty($attendance_records)): ?>
                                <div class="card mb-6">
                                    <div class="card-header flex justify-between items-center">
                                        <div><i class="fas fa-clock mr-1"></i> Attendance Records (<?php echo count($attendance_records); ?> records)</div>
                                        <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addAttendanceModal"><i class="fas fa-plus mr-1"></i> Add Attendance</button>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-striped table-bordered" id="attendanceRecordsTable">
                                                <thead>
                                                    <tr>
                                                        <th>Date</th><th>Employee</th><th>Department</th>
                                                        <th>Check In</th><th>Break Out</th><th>Break In</th><th>Check Out</th>
                                                        <th>Late (min)</th><th>OT (min)</th><th>Status</th><th>Remarks</th><th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($attendance_records as $record): ?>
                                                        <tr>
                                                            <td><?php echo date('m-d-Y', strtotime($record['attendance_date'])); ?></td>
                                                            <td><?php echo htmlspecialchars($record['employee_name']); ?><br><small class="text-muted">ID: <?php echo htmlspecialchars($record['emp_code']); ?></small></td>
                                                            <td><?php echo htmlspecialchars($record['department']); ?></td>
                                                            <td class="text-success-600! font-bold"><?php echo $record['check_in'] ? formatTimeTo12Hour($record['check_in']) : '--:--'; ?></td>
                                                            <td><?php echo $record['break_out'] ? formatTimeTo12Hour($record['break_out']) : '--:--'; ?></td>
                                                            <td><?php echo $record['break_in'] ? formatTimeTo12Hour($record['break_in']) : '--:--'; ?></td>
                                                            <td class="text-danger-600! font-bold"><?php echo $record['check_out'] ? formatTimeTo12Hour($record['check_out']) : '--:--'; ?></td>
                                                            <td class="<?php echo $record['late_time'] > 0 ? 'text-danger-600! font-bold' : ''; ?>"><?php echo $record['late_time'] > 0 ? $record['late_time'] : '-'; ?></td>
                                                            <td class="<?php echo $record['over_time'] > 0 ? 'text-success-600! font-bold' : ''; ?>"><?php echo $record['over_time'] > 0 ? $record['over_time'] : '-'; ?></td>
                                                            <td class="text-center">
                                                                <?php
                                                                $status = $record['status'] ?? 'Present';
                                                                $status_class = 'badge-success';
                                                                if ($status == 'Half Day') $status_class = 'badge-warning';
                                                                elseif ($status == 'Absent') $status_class = 'badge-danger';
                                                                ?>
                                                                <span class="badge <?php echo $status_class; ?>"><?php echo htmlspecialchars($status); ?></span>
                                                            </td>
                                                            <td><?php echo !empty($record['remarks']) ? htmlspecialchars($record['remarks']) : '-'; ?></td>
                                                            <td>
                                                                <div class="flex gap-1 whitespace-nowrap">
                                                                    <button type="button" class="btn btn-primary btn-sm shrink-0" onclick='editAttendance(<?php echo json_encode($record, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)' title="Edit"><i class="fas fa-edit"></i></button>
                                                                    <button type="button" class="btn btn-danger btn-sm shrink-0" onclick="deleteAttendance(<?php echo $record['id']; ?>, '<?php echo htmlspecialchars(addslashes($record['employee_name']), ENT_QUOTES); ?>', '<?php echo $record['attendance_date']; ?>')" title="Delete"><i class="fas fa-trash"></i></button>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                                <?php elseif (!isset($attendance_error) && empty($attendance_records)): ?>
                                <div class="card mb-6">
                                    <div class="card-header flex justify-between items-center">
                                        <div><i class="fas fa-clock mr-1"></i> Attendance Records</div>
                                        <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addAttendanceModal"><i class="fas fa-plus mr-1"></i> Add Attendance</button>
                                    </div>
                                    <div class="card-body"><div class="alert alert-info mb-0"><i class="fas fa-info-circle mr-2"></i> No attendance records found for the selected date range.</div></div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </main>
                <?php include 'includes/footer.php';?>
            </div>
        </div>

        <!-- Add Attendance Modal -->
        <div class="modal" id="addAttendanceModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header"><h5 class="modal-title"><i class="fas fa-plus-circle mr-2"></i>Add Attendance Record</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <form method="POST" id="addAttendanceForm">
                        <div class="modal-body">
                            <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                                <div class="min-w-0 mb-4">
                                    <label for="modal_employee_id" class="form-label">Employee <span class="text-danger">*</span></label>
                                    <select class="form-select" id="modal_employee_id" name="employee_id" required>
                                        <option value="">Select Employee</option>
                                        <?php foreach ($employees as $emp): ?>
                                            <option value="<?php echo $emp['id']; ?>"><?php echo htmlspecialchars($emp['firstname'] . ' ' . (!empty($emp['middlename']) ? substr($emp['middlename'], 0, 1) . '. ' : '') . $emp['lastname'] . (!empty($emp['suffix']) ? ' ' . $emp['suffix'] : '') . ' (ID: ' . $emp['employee_id'] . ')'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="min-w-0 mb-4">
                                    <label for="modal_attendance_date" class="form-label">Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="modal_attendance_date" name="attendance_date" value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 gap-6">
                                <div class="min-w-0 mb-4">
                                    <label for="modal_department" class="form-label">Department <span class="text-danger">*</span></label>
                                    <select class="form-select" id="modal_department" name="department" required>
                                        <option value="">Select Department</option>
                                        <?php foreach ($departments as $dept): ?>
                                            <option value="<?php echo htmlspecialchars($dept['department']); ?>"><?php echo htmlspecialchars($dept['department']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                                <div class="min-w-0 mb-4">
                                    <label class="form-label">Late Time (minutes)</label>
                                    <div class="flex items-center gap-2"><input type="number" class="form-control" id="modal_late_time" name="late_time" min="0" value="0" readonly><span class="text-sm text-slate-500">min</span></div>
                                    <div id="modal_calculation_info" class="text-xs mt-1 text-slate-500"></div>
                                </div>
                                <div class="min-w-0 mb-4">
                                    <label for="modal_over_time" class="form-label">Overtime (minutes) - Manual Input</label>
                                    <div class="flex items-center gap-2"><input type="number" class="form-control" id="modal_over_time" name="over_time" min="0" value="0"><span class="text-sm text-slate-500">min</span></div>
                                    <div class="text-xs mt-1 text-slate-500"><small class="text-muted">Enter overtime manually in minutes</small></div>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                                <div class="min-w-0 mb-4">
                                    <label for="modal_check_in" class="form-label">Check In (00:00-11:59) <small class="text-muted">Late if after 8:15 AM</small></label>
                                    <div class="time-input-wrapper relative">
                                        <input type="time" class="form-control" id="modal_check_in" name="check_in" step="1">
                                        <button type="button" class="time-clear-btn hidden absolute right-2.5 top-1/2 -translate-y-1/2 text-danger-600" onclick="clearTimeField('modal_check_in')"><i class="fas fa-times"></i></button>
                                    </div>
                                    <div id="modal_check_in_range_validation" class="text-xs mt-1 text-danger"></div>
                                </div>
                                <div class="min-w-0 mb-4">
                                    <label for="modal_break_out" class="form-label">Break Out (12:00-12:29)</label>
                                    <div class="time-input-wrapper relative">
                                        <input type="time" class="form-control" id="modal_break_out" name="break_out" step="1">
                                        <button type="button" class="time-clear-btn hidden absolute right-2.5 top-1/2 -translate-y-1/2 text-danger-600" onclick="clearTimeField('modal_break_out')"><i class="fas fa-times"></i></button>
                                    </div>
                                    <div id="modal_break_out_range_validation" class="text-xs mt-1 text-danger"></div>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                                <div class="min-w-0 mb-4">
                                    <label for="modal_break_in" class="form-label">Break In (12:30-14:30) <small class="text-muted">Late if after 1:15 PM</small></label>
                                    <div class="time-input-wrapper relative">
                                        <input type="time" class="form-control" id="modal_break_in" name="break_in" step="1">
                                        <button type="button" class="time-clear-btn hidden absolute right-2.5 top-1/2 -translate-y-1/2 text-danger-600" onclick="clearTimeField('modal_break_in')"><i class="fas fa-times"></i></button>
                                    </div>
                                    <div id="modal_break_in_range_validation" class="text-xs mt-1 text-danger"></div>
                                </div>
                                <div class="min-w-0 mb-4">
                                    <label for="modal_check_out" class="form-label">Check Out (17:00-23:59)</label>
                                    <div class="time-input-wrapper relative">
                                        <input type="time" class="form-control" id="modal_check_out" name="check_out" step="1">
                                        <button type="button" class="time-clear-btn hidden absolute right-2.5 top-1/2 -translate-y-1/2 text-danger-600" onclick="clearTimeField('modal_check_out')"><i class="fas fa-times"></i></button>
                                    </div>
                                    <div id="modal_check_out_range_validation" class="text-xs mt-1 text-danger"></div>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 gap-6"><div class="min-w-0 mb-4"><label for="modal_remarks" class="form-label">Remarks</label><input type="text" class="form-control" id="modal_remarks" name="remarks" placeholder="Enter remarks"></div></div>
                            <div id="time_validation_summary" class="alert alert-danger small" style="display:none"><i class="fas fa-exclamation-triangle mr-2"></i><span id="validation_summary_message"></span></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="add_attendance" class="btn btn-success"><i class="fas fa-save mr-1"></i> Save Attendance</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Edit Attendance Modal -->
        <div class="modal" id="editAttendanceModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header"><h5 class="modal-title"><i class="fas fa-edit mr-2"></i>Edit Attendance Record</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <form method="POST" id="editAttendanceForm">
                        <input type="hidden" name="attendance_id" id="edit_attendance_id">
                        <div class="modal-body">
                            <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                                <div class="min-w-0 mb-4">
                                    <label for="edit_employee_id" class="form-label">Employee <span class="text-danger">*</span></label>
                                    <select class="form-select" id="edit_employee_id" name="employee_id" required>
                                        <option value="">Select Employee</option>
                                        <?php foreach ($employees as $emp): ?>
                                            <option value="<?php echo $emp['id']; ?>"><?php echo htmlspecialchars($emp['firstname'] . ' ' . (!empty($emp['middlename']) ? substr($emp['middlename'], 0, 1) . '. ' : '') . $emp['lastname'] . (!empty($emp['suffix']) ? ' ' . $emp['suffix'] : '') . ' (ID: ' . $emp['employee_id'] . ')'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="min-w-0 mb-4">
                                    <label for="edit_attendance_date" class="form-label">Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="edit_attendance_date" name="attendance_date" required>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 gap-6">
                                <div class="min-w-0 mb-4">
                                    <label for="edit_department" class="form-label">Department <span class="text-danger">*</span></label>
                                    <select class="form-select" id="edit_department" name="department" required>
                                        <option value="">Select Department</option>
                                        <?php foreach ($departments as $dept): ?>
                                            <option value="<?php echo htmlspecialchars($dept['department']); ?>"><?php echo htmlspecialchars($dept['department']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                                <div class="min-w-0 mb-4">
                                    <label class="form-label">Late Time (minutes)</label>
                                    <div class="flex items-center gap-2"><input type="number" class="form-control" id="edit_late_time" name="late_time" min="0" value="0" readonly><span class="text-sm text-slate-500">min</span></div>
                                    <div id="edit_calculation_info" class="text-xs mt-1 text-slate-500"></div>
                                </div>
                                <div class="min-w-0 mb-4">
                                    <label for="edit_over_time" class="form-label">Overtime (minutes) - Manual Input</label>
                                    <div class="flex items-center gap-2"><input type="number" class="form-control" id="edit_over_time" name="over_time" min="0" value="0"><span class="text-sm text-slate-500">min</span></div>
                                    <div class="text-xs mt-1 text-slate-500"><small class="text-muted">Enter overtime manually in minutes</small></div>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                                <div class="min-w-0 mb-4">
                                    <label for="edit_check_in" class="form-label">Check In (00:00-11:59) <small class="text-muted">Late if after 8:15 AM</small></label>
                                    <div class="time-input-wrapper relative">
                                        <input type="time" class="form-control" id="edit_check_in" name="check_in" step="1">
                                        <button type="button" class="time-clear-btn hidden absolute right-2.5 top-1/2 -translate-y-1/2 text-danger-600" onclick="clearTimeField('edit_check_in')"><i class="fas fa-times"></i></button>
                                    </div>
                                    <div id="edit_check_in_range_validation" class="text-xs mt-1 text-danger"></div>
                                </div>
                                <div class="min-w-0 mb-4">
                                    <label for="edit_break_out" class="form-label">Break Out (12:00-12:29)</label>
                                    <div class="time-input-wrapper relative">
                                        <input type="time" class="form-control" id="edit_break_out" name="break_out" step="1">
                                        <button type="button" class="time-clear-btn hidden absolute right-2.5 top-1/2 -translate-y-1/2 text-danger-600" onclick="clearTimeField('edit_break_out')"><i class="fas fa-times"></i></button>
                                    </div>
                                    <div id="edit_break_out_range_validation" class="text-xs mt-1 text-danger"></div>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                                <div class="min-w-0 mb-4">
                                    <label for="edit_break_in" class="form-label">Break In (12:30-14:30) <small class="text-muted">Late if after 1:15 PM</small></label>
                                    <div class="time-input-wrapper relative">
                                        <input type="time" class="form-control" id="edit_break_in" name="break_in" step="1">
                                        <button type="button" class="time-clear-btn hidden absolute right-2.5 top-1/2 -translate-y-1/2 text-danger-600" onclick="clearTimeField('edit_break_in')"><i class="fas fa-times"></i></button>
                                    </div>
                                    <div id="edit_break_in_range_validation" class="text-xs mt-1 text-danger"></div>
                                </div>
                                <div class="min-w-0 mb-4">
                                    <label for="edit_check_out" class="form-label">Check Out (17:00-23:59)</label>
                                    <div class="time-input-wrapper relative">
                                        <input type="time" class="form-control" id="edit_check_out" name="check_out" step="1">
                                        <button type="button" class="time-clear-btn hidden absolute right-2.5 top-1/2 -translate-y-1/2 text-danger-600" onclick="clearTimeField('edit_check_out')"><i class="fas fa-times"></i></button>
                                    </div>
                                    <div id="edit_check_out_range_validation" class="text-xs mt-1 text-danger"></div>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 gap-6"><div class="min-w-0 mb-4"><label for="edit_remarks" class="form-label">Remarks</label><input type="text" class="form-control" id="edit_remarks" name="remarks" placeholder="Enter remarks"></div></div>
                            <div id="edit_time_validation_summary" class="alert alert-danger small" style="display:none"><i class="fas fa-exclamation-triangle mr-2"></i><span id="edit_validation_summary_message"></span></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="edit_attendance" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Update Attendance</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <div class="modal" id="deleteAttendanceModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-danger-600 text-white"><h5 class="modal-title"><i class="fas fa-exclamation-triangle mr-2"></i>Confirm Delete</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <form method="POST" id="deleteAttendanceForm">
                        <input type="hidden" name="attendance_id" id="delete_attendance_id">
                        <div class="modal-body">
                            <p>Are you sure you want to delete this attendance record?</p>
                            <p class="mb-0"><strong>Employee:</strong> <span id="delete_employee_name"></span></p>
                            <p><strong>Date:</strong> <span id="delete_attendance_date"></span></p>
                            <p class="text-danger"><small>This action cannot be undone.</small></p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="delete_attendance" class="btn btn-danger"><i class="fas fa-trash mr-1"></i> Delete Record</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>
        <?php
        /* Data island consumed by assets/js/upload_attendance.js. */
        $__ocp_data = [];
        /* missingEmployees */
        ob_start();
        include __DIR__ . "/includes/partials/upload_attendance/missingEmployees.php";
        $__ocp_data["missingEmployees"] = ob_get_clean();
        /* messageType = $message_type [guarded] */
        if ($message) {
            $__ocp_data["messageType"] = $message_type;
        }
        /* messageType2 = $message_type == 'success' ? 'Success!' : 'Error!' [guarded] */
        if ($message) {
            $__ocp_data["messageType2"] = $message_type == 'success' ? 'Success!' : 'Error!';
        }
        /* message = str_replace(["\r\n", "\n", "\r"], '<br>', addslashes($message)) [guarded] */
        if ($message) {
            $__ocp_data["message"] = str_replace(["\r\n", "\n", "\r"], '<br>', addslashes($message));
        }
        /* messageType3 = $message_type == 'success' ? 3000 : 5000 [guarded] */
        if ($message) {
            $__ocp_data["messageType3"] = $message_type == 'success' ? 3000 : 5000;
        }
        /* attendanceError = $attendance_error [guarded] */
        if (isset($attendance_error)) {
            $__ocp_data["attendanceError"] = $attendance_error;
        }
        $__ocp_data["today"] = date('Y-m-d');
        /* Flags for the script. It is a separate request, so it cannot test this
         * page's variables itself; these answer for it. Each is false on an ordinary
         * load, so nothing is shown unless there is something to show. */
        $__ocp_data["hasMessage"] = (!empty($message));
        $__ocp_data["hasAttendanceError"] = (isset($attendance_error));
        // The "No Records" notice: same test the script used to make in PHP, answered here
        // instead because the script is a separate request and cannot see these variables.
        $__ocp_data["showNoRecords"] = (!isset($attendance_error) && empty($attendance_records) && empty($message));
        ocp_page_data("upload_attendance", $__ocp_data);
        unset($__ocp_data);
        ?>
        <script src="<?php echo ocp_asset('assets/js/app.js'); ?>"></script>
        <script src="assets/js/upload_attendance.js.php"></script>
    </body>
</html>
