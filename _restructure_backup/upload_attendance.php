<?php
session_start();
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Database connection
require_once 'includes/db_config.php';

// Load PhpSpreadsheet library
require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

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

// Initialize variables
$message = '';
$message_type = '';
$uploaded_data = [];
$selected_date_from = date('Y-m-d');
$selected_date_to = date('Y-m-d');

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Handle date range selection for viewing
    if (isset($_POST['select_date_range'])) {
        $selected_date_from = $_POST['date_from'];
        $selected_date_to = $_POST['date_to'];
    }
    
    // Handle delete attendance
    if (isset($_POST['delete_attendance'])) {
        $attendance_id = $_POST['attendance_id'];
        
        try {
            $deleteStmt = $pdo->prepare("DELETE FROM attendance WHERE id = :id");
            $deleteStmt->bindParam(':id', $attendance_id);
            
            if ($deleteStmt->execute()) {
                $message = 'Attendance record deleted successfully.';
                $message_type = 'success';
            } else {
                $message = 'Failed to delete attendance record.';
                $message_type = 'error';
            }
        } catch (PDOException $e) {
            $message = 'Database error: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
    
    // Handle edit attendance
    if (isset($_POST['edit_attendance'])) {
        $attendance_id = $_POST['attendance_id'];
        $employee_id = $_POST['employee_id'];
        $department = $_POST['department'];
        $attendance_date = $_POST['attendance_date'];
        
        // Handle empty time fields - convert empty strings to NULL
        $check_in = (!empty($_POST['check_in']) && trim($_POST['check_in']) !== '') ? $_POST['check_in'] : null;
        $break_out = (!empty($_POST['break_out']) && trim($_POST['break_out']) !== '') ? $_POST['break_out'] : null;
        $break_in = (!empty($_POST['break_in']) && trim($_POST['break_in']) !== '') ? $_POST['break_in'] : null;
        $check_out = (!empty($_POST['check_out']) && trim($_POST['check_out']) !== '') ? $_POST['check_out'] : null;
        $remarks = $_POST['remarks'];
        
        // Get overtime as manual input (allow 0 or empty)
        $over_time = (!empty($_POST['over_time']) && trim($_POST['over_time']) !== '') ? intval($_POST['over_time']) : 0;
        
        // Calculate late time based on check-in (after 8:15 AM) and break-in (after 1:15 PM)
        $late_time = calculateLateTime($check_in, $break_in);
        
        // Calculate status based on attendance times
        $status = calculateAttendanceStatus($check_in, $break_out, $break_in, $check_out);
        
        try {
            // Get employee details
            $empStmt = $pdo->prepare("SELECT id, firstname, middlename, lastname, suffix, employee_id FROM employee WHERE id = :id AND status = 'active'");
            $empStmt->bindParam(':id', $employee_id);
            $empStmt->execute();
            $employee = $empStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$employee) {
                $message = 'Employee not found.';
                $message_type = 'error';
            } else {
                // Format employee name
                $formatted_name = formatEmployeeName($employee['firstname'], $employee['middlename'], $employee['lastname'], $employee['suffix']);
                
                // Update existing record
                $updateStmt = $pdo->prepare("
                    UPDATE attendance SET
                        employee_id = :employee_id,
                        employee_name = :employee_name,
                        department = :department,
                        attendance_date = :attendance_date,
                        check_in = :check_in,
                        break_out = :break_out,
                        break_in = :break_in,
                        check_out = :check_out,
                        late_time = :late_time,
                        over_time = :over_time,
                        status = :status,
                        remarks = :remarks,
                        updated_at = CURRENT_TIMESTAMP,
                        updated_by = :updated_by
                    WHERE id = :id
                ");
                
                $updateStmt->bindParam(':employee_id', $employee['id']);
                $updateStmt->bindParam(':employee_name', $formatted_name);
                $updateStmt->bindParam(':department', $department);
                $updateStmt->bindParam(':attendance_date', $attendance_date);
                $updateStmt->bindParam(':check_in', $check_in);
                $updateStmt->bindParam(':break_out', $break_out);
                $updateStmt->bindParam(':break_in', $break_in);
                $updateStmt->bindParam(':check_out', $check_out);
                $updateStmt->bindParam(':late_time', $late_time);
                $updateStmt->bindParam(':over_time', $over_time);
                $updateStmt->bindParam(':status', $status);
                $updateStmt->bindParam(':remarks', $remarks);
                $updateStmt->bindParam(':updated_by', $user_id);
                $updateStmt->bindParam(':id', $attendance_id);
                
                if ($updateStmt->execute()) {
                    $message = 'Attendance record updated successfully.';
                    $message_type = 'success';
                } else {
                    $message = 'Failed to update attendance record.';
                    $message_type = 'error';
                }
            }
        } catch (PDOException $e) {
            $message = 'Database error: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
    
    // Handle manual attendance addition
    if (isset($_POST['add_attendance'])) {
        $employee_id = $_POST['employee_id'];
        $department = $_POST['department'];
        $attendance_date = $_POST['attendance_date'];
        
        // Handle empty time fields - convert empty strings to NULL
        $check_in = (!empty($_POST['check_in']) && trim($_POST['check_in']) !== '') ? $_POST['check_in'] : null;
        $break_out = (!empty($_POST['break_out']) && trim($_POST['break_out']) !== '') ? $_POST['break_out'] : null;
        $break_in = (!empty($_POST['break_in']) && trim($_POST['break_in']) !== '') ? $_POST['break_in'] : null;
        $check_out = (!empty($_POST['check_out']) && trim($_POST['check_out']) !== '') ? $_POST['check_out'] : null;
        $remarks = $_POST['remarks'];
        
        // Get overtime as manual input (allow 0 or empty)
        $over_time = (!empty($_POST['over_time']) && trim($_POST['over_time']) !== '') ? intval($_POST['over_time']) : 0;
        
        // Calculate late time based on check-in (after 8:15 AM) and break-in (after 1:15 PM)
        $late_time = calculateLateTime($check_in, $break_in);
        
        // Calculate status based on attendance times
        $status = calculateAttendanceStatus($check_in, $break_out, $break_in, $check_out);
        
        try {
            // Get employee details
            $empStmt = $pdo->prepare("SELECT id, firstname, middlename, lastname, suffix, employee_id FROM employee WHERE id = :id AND status = 'active'");
            $empStmt->bindParam(':id', $employee_id);
            $empStmt->execute();
            $employee = $empStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$employee) {
                $message = 'Employee not found.';
                $message_type = 'error';
            } else {
                // Format employee name
                $formatted_name = formatEmployeeName($employee['firstname'], $employee['middlename'], $employee['lastname'], $employee['suffix']);
                
                // Check if record already exists
                $checkStmt = $pdo->prepare("
                    SELECT id FROM attendance 
                    WHERE employee_id = :employee_id AND attendance_date = :attendance_date
                ");
                $checkStmt->bindParam(':employee_id', $employee['id']);
                $checkStmt->bindParam(':attendance_date', $attendance_date);
                $checkStmt->execute();
                
                if ($checkStmt->rowCount() > 0) {
                    // Update existing record
                    $updateStmt = $pdo->prepare("
                        UPDATE attendance SET
                            department = :department,
                            check_in = :check_in,
                            break_out = :break_out,
                            break_in = :break_in,
                            check_out = :check_out,
                            late_time = :late_time,
                            over_time = :over_time,
                            status = :status,
                            remarks = :remarks,
                            updated_at = CURRENT_TIMESTAMP,
                            updated_by = :updated_by
                        WHERE employee_id = :employee_id AND attendance_date = :attendance_date
                    ");
                    
                    $updateStmt->bindParam(':department', $department);
                    $updateStmt->bindParam(':check_in', $check_in);
                    $updateStmt->bindParam(':break_out', $break_out);
                    $updateStmt->bindParam(':break_in', $break_in);
                    $updateStmt->bindParam(':check_out', $check_out);
                    $updateStmt->bindParam(':late_time', $late_time);
                    $updateStmt->bindParam(':over_time', $over_time);
                    $updateStmt->bindParam(':status', $status);
                    $updateStmt->bindParam(':remarks', $remarks);
                    $updateStmt->bindParam(':updated_by', $user_id);
                    $updateStmt->bindParam(':employee_id', $employee['id']);
                    $updateStmt->bindParam(':attendance_date', $attendance_date);
                    
                    if ($updateStmt->execute()) {
                        $message = 'Attendance record updated successfully.';
                        $message_type = 'success';
                    } else {
                        $message = 'Failed to update attendance record.';
                        $message_type = 'error';
                    }
                } else {
                    // Insert new record
                    $insertStmt = $pdo->prepare("
                        INSERT INTO attendance (
                            employee_id, employee_name, department, attendance_date,
                            check_in, break_out, break_in, check_out,
                            late_time, over_time, status, remarks, created_by
                        ) VALUES (
                            :employee_id, :employee_name, :department, :attendance_date,
                            :check_in, :break_out, :break_in, :check_out,
                            :late_time, :over_time, :status, :remarks, :created_by
                        )
                    ");
                    
                    $insertStmt->bindParam(':employee_id', $employee['id']);
                    $insertStmt->bindParam(':employee_name', $formatted_name);
                    $insertStmt->bindParam(':department', $department);
                    $insertStmt->bindParam(':attendance_date', $attendance_date);
                    $insertStmt->bindParam(':check_in', $check_in);
                    $insertStmt->bindParam(':break_out', $break_out);
                    $insertStmt->bindParam(':break_in', $break_in);
                    $insertStmt->bindParam(':check_out', $check_out);
                    $insertStmt->bindParam(':late_time', $late_time);
                    $insertStmt->bindParam(':over_time', $over_time);
                    $insertStmt->bindParam(':status', $status);
                    $insertStmt->bindParam(':remarks', $remarks);
                    $insertStmt->bindParam(':created_by', $user_id);
                    
                    if ($insertStmt->execute()) {
                        $message = 'Attendance record added successfully.';
                        $message_type = 'success';
                    } else {
                        $message = 'Failed to add attendance record.';
                        $message_type = 'error';
                    }
                }
            }
        } catch (PDOException $e) {
            $message = 'Database error: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
    
    // Handle file upload
    if (isset($_FILES['attendance_file']) && $_FILES['attendance_file']['error'] == 0) {
        
        $file = $_FILES['attendance_file'];
        $file_name = $file['name'];
        $file_tmp = $file['tmp_name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        // Validate file extension
        if (!in_array($file_ext, ['xls', 'xlsx', 'csv'])) {
            $message = 'Only Excel files (.xls, .xlsx) and CSV files are allowed.';
            $message_type = 'error';
        } else {
            
            // Parse based on file type
            if ($file_ext == 'csv') {
                // Parse CSV file
                $parsed_data = parseCSVFile($file_tmp);
            } else {
                // Parse Excel file using PhpSpreadsheet
                $parsed_data = parseExcelFile($file_tmp);
            }
            
            if ($parsed_data['success']) {
                // Save to database with missing employees info
                $result = saveAttendanceToDatabase($pdo, $parsed_data['data'], $_SESSION['user_id']);
                
                if ($result['success']) {
                    $message = $result['message'];
                    $message_type = 'success';
                    $uploaded_data = $result['data'];
                    
                    // Update date range from the uploaded data
                    if (!empty($uploaded_data)) {
                        $dates = array_column($uploaded_data, 'date');
                        $selected_date_from = min($dates);
                        $selected_date_to = max($dates);
                    }
                    
                    // Store missing employees info in session for JavaScript alert
                    if (!empty($result['missing_employees'])) {
                        $_SESSION['missing_employees_alert'] = $result['missing_employees'];
                        $_SESSION['success_count'] = $result['success_count'];
                        $_SESSION['missing_count'] = $result['missing_count'];
                    }
                } else {
                    $message = $result['message'];
                    $message_type = 'error';
                }
            } else {
                $message = $parsed_data['message'];
                $message_type = 'error';
            }
        }
    }
}

/**
 * Extract attendance times from a list of time entries
 * 
 * Rules:
 * - check_in: First time between 00:00 and 11:59
 * - break_out: First time between 12:00 and 12:29
 * - break_in: First time between 12:30 and 14:30
 * - check_out: First time between 17:00 and 23:59
 * 
 * @param array $time_list Array of time strings
 * @return array Associative array with check_in, break_out, break_in, check_out
 */
function extractAttendanceTimes($time_list) {
    $result = [
        'check_in' => null,
        'break_out' => null,
        'break_in' => null,
        'check_out' => null
    ];
    
    if (empty($time_list)) {
        return $result;
    }
    
    // Sort times chronologically
    $sorted_times = [];
    foreach ($time_list as $time) {
        $minutes = timeToMinutes($time);
        if ($minutes !== null) {
            $sorted_times[] = [
                'time' => $time,
                'minutes' => $minutes
            ];
        }
    }
    
    // Sort by minutes since midnight
    usort($sorted_times, function($a, $b) {
        return $a['minutes'] - $b['minutes'];
    });
    
    // Extract check_in: First time between 00:00 (0 min) and 11:59 (719 min)
    foreach ($sorted_times as $entry) {
        if ($entry['minutes'] >= 0 && $entry['minutes'] <= 11 * 60 + 59) {
            $result['check_in'] = $entry['time'];
            break;
        }
    }
    
    // Extract break_out: First time between 12:00 (720 min) and 12:29 (749 min)
    foreach ($sorted_times as $entry) {
        if ($entry['minutes'] >= 12 * 60 && $entry['minutes'] <= 12 * 60 + 29) {
            $result['break_out'] = $entry['time'];
            break;
        }
    }
    
    // Extract break_in: First time between 12:30 (750 min) and 14:30 (870 min)
    foreach ($sorted_times as $entry) {
        if ($entry['minutes'] >= 12 * 60 + 30 && $entry['minutes'] <= 14 * 60 + 30) {
            $result['break_in'] = $entry['time'];
            break;
        }
    }
    
    // Extract check_out: First time between 17:00 (1020 min) and 23:59 (1439 min)
    foreach ($sorted_times as $entry) {
        if ($entry['minutes'] >= 17 * 60 && $entry['minutes'] <= 23 * 60 + 59) {
            $result['check_out'] = $entry['time'];
            break;
        }
    }
    
    return $result;
}

/**
 * Calculate late time minutes
 * - check_in is late if AFTER 8:15 AM (8:16 or later)
 * - break_in is late if AFTER 1:15 PM (1:16 or later)
 * 
 * @param string|null $check_in
 * @param string|null $break_in
 * @return int Total late minutes
 */
function calculateLateTime($check_in, $break_in) {
    $total_late = 0;
    
    // Standard times in minutes since midnight
    // Check-in cutoff: 8:15 AM (on time up to 8:15, late starting at 8:16)
    $CHECK_IN_CUTOFF = 8 * 60 + 15; // 08:15 = 495 minutes
    
    // Break-in cutoff: 1:15 PM (on time up to 1:15, late starting at 1:16)
    $BREAK_IN_CUTOFF = 13 * 60 + 15; // 13:15 = 795 minutes
    
    // Calculate late from check-in (AFTER 8:15 AM, so 8:16 and later is late)
    if (!empty($check_in)) {
        $checkInMinutes = timeToMinutes($check_in);
        if ($checkInMinutes !== null && $checkInMinutes > $CHECK_IN_CUTOFF) {
            $total_late += ($checkInMinutes - $CHECK_IN_CUTOFF);
        }
    }
    
    // Calculate late from break-in (AFTER 1:15 PM, so 1:16 and later is late)
    if (!empty($break_in)) {
        $breakInMinutes = timeToMinutes($break_in);
        if ($breakInMinutes !== null && $breakInMinutes > $BREAK_IN_CUTOFF) {
            $total_late += ($breakInMinutes - $BREAK_IN_CUTOFF);
        }
    }
    
    return $total_late;
}

/**
 * Convert time string to minutes since midnight
 * 
 * @param string $timeStr Time string
 * @return int|null Minutes since midnight or null if invalid
 */
function timeToMinutes($timeStr) {
    if (empty($timeStr)) {
        return null;
    }
    
    $timeStr = trim(preg_replace('/[\r\n]+/', '', $timeStr));
    
    $parts = explode(':', $timeStr);
    if (count($parts) < 2) {
        return null;
    }
    
    $hours = (int)$parts[0];
    $minutes = (int)$parts[1];
    $seconds = isset($parts[2]) ? (int)$parts[2] : 0;
    
    if ($hours < 0 || $hours > 23 || $minutes < 0 || $minutes > 59 || $seconds < 0 || $seconds > 59) {
        return null;
    }
    
    return ($hours * 60) + $minutes;
}

/**
 * Format time to 12-hour format with AM/PM
 */
function formatTimeTo12Hour($timeStr) {
    if (empty($timeStr)) {
        return '';
    }
    
    $timeStr = trim(preg_replace('/[\r\n]+/', '', $timeStr));
    
    $parts = explode(':', $timeStr);
    if (count($parts) >= 2) {
        $hours = (int)$parts[0];
        $minutes = $parts[1];
        $seconds = isset($parts[2]) ? $parts[2] : '00';
        
        $ampm = $hours >= 12 ? 'PM' : 'AM';
        $hours12 = $hours % 12;
        $hours12 = $hours12 ? $hours12 : 12;
        
        return sprintf("%d:%s:%s %s", $hours12, $minutes, $seconds, $ampm);
    }
    
    return $timeStr;
}

/**
 * Calculate attendance status based on time entries
 */
function calculateAttendanceStatus($check_in, $break_out, $break_in, $check_out) {
    $has_check_in = !empty($check_in);
    $has_break_out = !empty($break_out);
    $has_break_in = !empty($break_in);
    $has_check_out = !empty($check_out);
    
    $time_count = 0;
    if ($has_check_in) $time_count++;
    if ($has_break_out) $time_count++;
    if ($has_break_in) $time_count++;
    if ($has_check_out) $time_count++;
    
    if ($time_count === 0) return 'Absent';
    if ($time_count === 1) return 'Absent';
    if ($has_check_in && $has_break_out && $has_break_in && $has_check_out) return 'Present';
    if ($has_check_in && $has_check_out && !$has_break_out && !$has_break_in) return 'Present';
    if ($has_check_in && $has_break_out && !$has_break_in && !$has_check_out) return 'Half Day';
    if (!$has_check_in && !$has_break_out && $has_break_in && $has_check_out) return 'Half Day';
    if ($has_check_in && !$has_break_out && $has_break_in && !$has_check_out) return 'Half Day';
    if ($has_check_in && $has_break_out && $has_break_in && !$has_check_out) return 'Half Day';
    if (!$has_check_in && $has_break_out && $has_break_in && $has_check_out) return 'Half Day';
    if ($has_check_in && $time_count === 2) return 'Half Day';
    if ($has_check_out && $time_count === 2 && !$has_check_in) return 'Half Day';
    
    return 'Present';
}

/**
 * Parse CSV file
 */
function parseCSVFile($file_path) {
    $result = [
        'success' => false,
        'message' => '',
        'data' => []
    ];
    
    $handle = fopen($file_path, "r");
    $all_data = [];
    while (($data = fgetcsv($handle)) !== FALSE) {
        $all_data[] = $data;
    }
    fclose($handle);
    
    return parseAttendanceData($all_data);
}

/**
 * Parse Excel file using PhpSpreadsheet
 */
function parseExcelFile($file_path) {
    $result = [
        'success' => false,
        'message' => '',
        'data' => []
    ];
    
    try {
        $spreadsheet = IOFactory::load($file_path);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();
        
        return parseAttendanceData($rows);
        
    } catch (Exception $e) {
        $result['message'] = 'Error parsing Excel file: ' . $e->getMessage();
        return $result;
    }
}

/**
 * Parse attendance data from array format
 */
function parseAttendanceData($rows) {
    $result = [
        'success' => false,
        'message' => '',
        'data' => []
    ];
    
    if (empty($rows) || count($rows) < 6) {
        $result['message'] = 'File is empty or has invalid format.';
        return $result;
    }
    
    try {
        $header_row_index = -1;
        $header_columns = [];
        
        foreach ($rows as $index => $row) {
            $cleaned_row = array_map(function($cell) {
                return trim(strval($cell));
            }, $row);
            
            if (isset($cleaned_row[0]) && $cleaned_row[0] == 'Employee ID' &&
                isset($cleaned_row[1]) && $cleaned_row[1] == 'Name' &&
                isset($cleaned_row[2]) && $cleaned_row[2] == 'Department') {
                $header_row_index = $index;
                $header_columns = $cleaned_row;
                break;
            }
        }
        
        if ($header_row_index == -1) {
            $result['message'] = 'Could not find header row (Employee ID, Name, Department).';
            return $result;
        }
        
        $day_columns = [];
        $start_date = null;
        
        if (isset($rows[3])) {
            $made_date_str = trim(strval($rows[3][0]));
            if (strpos($made_date_str, 'Made Date:') === 0) {
                $date_part = substr($made_date_str, strlen('Made Date:'));
                $date_range = explode('-', $date_part);
                if (count($date_range) >= 2) {
                    $start_date_str = trim($date_range[0]);
                    $start_date = date('Y-m-d', strtotime($start_date_str));
                }
            }
        }
        
        if (!$start_date) {
            $start_date = date('Y-m-01');
        }
        
        foreach ($header_columns as $idx => $value) {
            if ($idx >= 3 && is_numeric($value)) {
                $day_num = intval($value);
                if ($day_num >= 1 && $day_num <= 31) {
                    $day_columns[$idx] = $day_num;
                }
            }
        }
        
        if (empty($day_columns)) {
            $result['message'] = 'No day columns found.';
            return $result;
        }
        
        for ($i = $header_row_index + 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            $cleaned_row = array_map(function($cell) {
                return trim(strval($cell));
            }, $row);
            
            if (empty(array_filter($cleaned_row))) continue;
            if (!isset($cleaned_row[0]) || !is_numeric($cleaned_row[0])) continue;
            
            $employee_id_str = trim($cleaned_row[0]);
            $name = isset($cleaned_row[1]) ? trim($cleaned_row[1]) : '';
            $department = isset($cleaned_row[2]) ? trim($cleaned_row[2]) : '';
            
            if (empty($employee_id_str)) continue;
            
            foreach ($day_columns as $col_idx => $day_num) {
                $cell_value = isset($cleaned_row[$col_idx]) ? trim($cleaned_row[$col_idx]) : '';
                if (empty($cell_value)) continue;
                
                $time_parts = [];
                
                if (stripos($cell_value, '<br') !== false) {
                    $time_parts = preg_split('/<br\s*\/?>/i', $cell_value, -1, PREG_SPLIT_NO_EMPTY);
                } else {
                    $time_parts = preg_split('/[\r\n]+/', $cell_value, -1, PREG_SPLIT_NO_EMPTY);
                }
                
                $time_parts = array_map('trim', $time_parts);
                $time_parts = array_filter($time_parts, function($val) {
                    $val = trim($val);
                    return !empty($val) && preg_match('/^[\d:]+$/', $val);
                });
                $time_parts = array_values($time_parts);
                
                $clean_times = [];
                foreach ($time_parts as $time) {
                    $cleaned = convertExcelTime($time);
                    if ($cleaned !== null) {
                        $clean_times[] = $cleaned;
                    }
                }
                
                // Use extractAttendanceTimes to get the correct times
                $extracted = extractAttendanceTimes($clean_times);
                
                $check_in = $extracted['check_in'];
                $break_out = $extracted['break_out'];
                $break_in = $extracted['break_in'];
                $check_out = $extracted['check_out'];
                
                $date_obj = new DateTime($start_date);
                $date_obj->modify('+' . ($day_num - 1) . ' days');
                $date = $date_obj->format('Y-m-d');
                
                $late_time = calculateLateTime($check_in, $break_in);
                // Overtime will be 0 for uploaded files (manual input required)
                $over_time = 0;
                
                $result['data'][] = [
                    'employee_id' => $employee_id_str,
                    'name' => $name,
                    'department' => $department,
                    'date' => $date,
                    'check_in' => $check_in,
                    'break_out' => $break_out,
                    'break_in' => $break_in,
                    'check_out' => $check_out,
                    'late_time' => $late_time,
                    'over_time' => $over_time,
                    'remarks' => ''
                ];
            }
        }
        
        if (empty($result['data'])) {
            $result['message'] = 'No valid attendance records found in the file.';
            return $result;
        }
        
        $result['success'] = true;
        $result['message'] = 'Successfully parsed ' . count($result['data']) . ' attendance records.';
        
    } catch (Exception $e) {
        $result['message'] = 'Error parsing file: ' . $e->getMessage();
    }
    
    return $result;
}

/**
 * Convert Excel time value to HH:MM:SS format
 */
function convertExcelTime($value) {
    if (empty($value) || $value === null || $value === '') return null;
    
    if (is_string($value)) {
        $value = trim(preg_replace('/[\r\n]+/', '', $value));
    }
    
    if ($value == '--:--') return null;
    
    if (preg_match('/^([0-1][0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/', $value)) {
        if ($value == '00:00:00' || $value == '00:00') return null;
        return $value;
    }
    
    if (is_numeric($value) && $value > 0 && $value < 1) {
        $total_seconds = round($value * 24 * 60 * 60);
        if ($total_seconds <= 0) return null;
        
        $hours = floor($total_seconds / 3600);
        $minutes = floor(($total_seconds % 3600) / 60);
        $seconds = $total_seconds % 60;
        
        if ($hours == 0 && $minutes == 0) return null;
        return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    }
    
    if ($value === '0' || $value === 0) return null;
    
    return $value;
}

/**
 * Format employee name
 */
function formatEmployeeName($firstname, $middlename, $lastname, $suffix = null) {
    $formatted_name = $firstname;
    if (!empty($middlename)) {
        $formatted_name .= ' ' . substr($middlename, 0, 1) . '.';
    }
    $formatted_name .= ' ' . $lastname;
    if (!empty($suffix)) {
        $formatted_name .= ' ' . $suffix;
    }
    return $formatted_name;
}

/**
 * Save attendance data to database
 */
function saveAttendanceToDatabase($pdo, $data, $user_id) {
    $result = [
        'success' => false,
        'message' => '',
        'data' => [],
        'missing_employees' => [],
        'success_count' => 0,
        'missing_count' => 0
    ];
    
    try {
        $pdo->beginTransaction();
        
        $employeesStmt = $pdo->prepare("SELECT id, employee_id, firstname, middlename, lastname, suffix FROM employee WHERE status = 'active'");
        $employeesStmt->execute();
        $employees = $employeesStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $employee_by_code = [];
        $employee_by_formatted_name = [];
        foreach ($employees as $emp) {
            $employee_by_code[$emp['employee_id']] = $emp;
            $formatted_name = formatEmployeeName($emp['firstname'], $emp['middlename'], $emp['lastname'], $emp['suffix']);
            $employee_by_formatted_name[strtolower($formatted_name)] = $emp;
            $simple_name = strtolower($emp['firstname'] . ' ' . $emp['lastname']);
            $employee_by_formatted_name[$simple_name] = $emp;
        }
        
        $success_count = 0;
        $error_count = 0;
        $errors = [];
        $saved_data = [];
        $missing_employees = [];
        
        foreach ($data as $record) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $record['date'])) {
                $error_count++;
                $errors[] = "Invalid date format for employee {$record['name']}: {$record['date']}";
                continue;
            }
            
            $employee = null;
            if (isset($employee_by_code[$record['employee_id']])) {
                $employee = $employee_by_code[$record['employee_id']];
            } else {
                $name_lower = strtolower(trim($record['name']));
                if (isset($employee_by_formatted_name[$name_lower])) {
                    $employee = $employee_by_formatted_name[$name_lower];
                } else {
                    foreach ($employees as $emp) {
                        $formatted_name = strtolower(formatEmployeeName($emp['firstname'], $emp['middlename'], $emp['lastname'], $emp['suffix']));
                        $simple_name = strtolower($emp['firstname'] . ' ' . $emp['lastname']);
                        
                        if (strpos($formatted_name, $name_lower) !== false || 
                            strpos($name_lower, $formatted_name) !== false ||
                            strpos($simple_name, $name_lower) !== false || 
                            strpos($name_lower, $simple_name) !== false) {
                            $employee = $emp;
                            break;
                        }
                        
                        $name_parts = explode(' ', $record['name']);
                        if (count($name_parts) >= 2) {
                            $upload_firstname = strtolower($name_parts[0]);
                            $upload_lastname = strtolower(end($name_parts));
                            
                            if ((strpos(strtolower($emp['firstname']), $upload_firstname) !== false || 
                                 strpos($upload_firstname, strtolower($emp['firstname'])) !== false) &&
                                (strpos(strtolower($emp['lastname']), $upload_lastname) !== false || 
                                 strpos($upload_lastname, strtolower($emp['lastname'])) !== false)) {
                                $employee = $emp;
                                break;
                            }
                        }
                    }
                }
            }
            
            if (!$employee) {
                $missing_employees[] = [
                    'employee_id' => $record['employee_id'],
                    'name' => $record['name'],
                    'date' => $record['date']
                ];
                continue;
            }
            
            $formatted_employee_name = formatEmployeeName(
                $employee['firstname'], 
                $employee['middlename'], 
                $employee['lastname'], 
                $employee['suffix']
            );
            
            $status = calculateAttendanceStatus(
                $record['check_in'], 
                $record['break_out'], 
                $record['break_in'], 
                $record['check_out']
            );
            
            $clean_check_in = cleanTimeValue($record['check_in']);
            $clean_break_out = cleanTimeValue($record['break_out']);
            $clean_break_in = cleanTimeValue($record['break_in']);
            $clean_check_out = cleanTimeValue($record['check_out']);
            
            $late_time = calculateLateTime($clean_check_in, $clean_break_in);
            // Use the overtime from the record (will be 0 for uploaded files)
            $over_time = $record['over_time'] ?? 0;
            
            $checkStmt = $pdo->prepare("
                SELECT id FROM attendance 
                WHERE employee_id = :employee_id AND attendance_date = :attendance_date
            ");
            $checkStmt->bindParam(':employee_id', $employee['id']);
            $checkStmt->bindParam(':attendance_date', $record['date']);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() > 0) {
                $updateStmt = $pdo->prepare("
                    UPDATE attendance SET
                        employee_name = :employee_name,
                        department = :department,
                        check_in = :check_in,
                        break_out = :break_out,
                        break_in = :break_in,
                        check_out = :check_out,
                        late_time = :late_time,
                        over_time = :over_time,
                        status = :status,
                        remarks = :remarks,
                        updated_at = CURRENT_TIMESTAMP,
                        updated_by = :updated_by
                    WHERE employee_id = :employee_id AND attendance_date = :attendance_date
                ");
                
                $updateStmt->bindParam(':employee_name', $formatted_employee_name);
                $updateStmt->bindParam(':department', $record['department']);
                $updateStmt->bindParam(':check_in', $clean_check_in);
                $updateStmt->bindParam(':break_out', $clean_break_out);
                $updateStmt->bindParam(':break_in', $clean_break_in);
                $updateStmt->bindParam(':check_out', $clean_check_out);
                $updateStmt->bindParam(':late_time', $late_time);
                $updateStmt->bindParam(':over_time', $over_time);
                $updateStmt->bindParam(':status', $status);
                $updateStmt->bindParam(':remarks', $record['remarks']);
                $updateStmt->bindParam(':updated_by', $user_id);
                $updateStmt->bindParam(':employee_id', $employee['id']);
                $updateStmt->bindParam(':attendance_date', $record['date']);
                
                if ($updateStmt->execute()) {
                    $success_count++;
                    $record['db_status'] = 'Updated';
                    $saved_data[] = $record;
                } else {
                    $error_count++;
                    $error_info = $updateStmt->errorInfo();
                    $errors[] = "Failed to update record: " . $error_info[2];
                }
            } else {
                $insertStmt = $pdo->prepare("
                    INSERT INTO attendance (
                        employee_id, employee_name, department, attendance_date,
                        check_in, break_out, break_in, check_out,
                        late_time, over_time, status, remarks, created_by
                    ) VALUES (
                        :employee_id, :employee_name, :department, :attendance_date,
                        :check_in, :break_out, :break_in, :check_out,
                        :late_time, :over_time, :status, :remarks, :created_by
                    )
                ");
                
                $insertStmt->bindParam(':employee_id', $employee['id']);
                $insertStmt->bindParam(':employee_name', $formatted_employee_name);
                $insertStmt->bindParam(':department', $record['department']);
                $insertStmt->bindParam(':attendance_date', $record['date']);
                $insertStmt->bindParam(':check_in', $clean_check_in);
                $insertStmt->bindParam(':break_out', $clean_break_out);
                $insertStmt->bindParam(':break_in', $clean_break_in);
                $insertStmt->bindParam(':check_out', $clean_check_out);
                $insertStmt->bindParam(':late_time', $late_time);
                $insertStmt->bindParam(':over_time', $over_time);
                $insertStmt->bindParam(':status', $status);
                $insertStmt->bindParam(':remarks', $record['remarks']);
                $insertStmt->bindParam(':created_by', $user_id);
                
                if ($insertStmt->execute()) {
                    $success_count++;
                    $record['db_status'] = 'Inserted';
                    $saved_data[] = $record;
                } else {
                    $error_count++;
                    $error_info = $insertStmt->errorInfo();
                    $errors[] = "Failed to insert record: " . $error_info[2];
                }
            }
        }
        
        $pdo->commit();
        
        $result['success'] = true;
        $result['data'] = $saved_data;
        $result['missing_employees'] = $missing_employees;
        $result['success_count'] = $success_count;
        $result['missing_count'] = count($missing_employees);
        
        $message_parts = [];
        if ($success_count > 0) {
            $message_parts[] = "Successfully saved $success_count attendance records.";
        }
        if (count($missing_employees) > 0) {
            $message_parts[] = count($missing_employees) . " employee(s) not found in the system.";
        }
        if ($error_count > 0) {
            $message_parts[] = "$error_count error(s) occurred.";
        }
        
        $result['message'] = implode(' ', $message_parts);
        
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $result['message'] = 'Database error: ' . $e->getMessage();
    }
    
    return $result;
}

/**
 * Clean time value
 */
function cleanTimeValue($timeValue) {
    if (empty($timeValue)) return null;
    
    $cleaned = preg_replace('/[\r\n]+/', '', $timeValue);
    $cleaned = trim($cleaned);
    
    if (empty($cleaned)) return null;
    
    if (preg_match('/^([0-1][0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/', $cleaned)) {
        return $cleaned;
    }
    
    return null;
}

// Fetch attendance records for the selected date range
try {
    $attendanceStmt = $pdo->prepare("
        SELECT a.*, e.employee_id as emp_code
        FROM attendance a
        JOIN employee e ON a.employee_id = e.id
        WHERE a.attendance_date BETWEEN :date_from AND :date_to
        ORDER BY a.attendance_date DESC, a.employee_name ASC
    ");
    $attendanceStmt->bindParam(':date_from', $selected_date_from);
    $attendanceStmt->bindParam(':date_to', $selected_date_to);
    $attendanceStmt->execute();
    $attendance_records = $attendanceStmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $attendance_records = [];
    $attendance_error = "Error fetching attendance records: " . $e->getMessage();
}

// Get employees for dropdown
try {
    $employeesStmt = $pdo->prepare("SELECT id, employee_id, firstname, middlename, lastname, suffix FROM employee WHERE status = 'active' ORDER BY firstname ASC");
    $employeesStmt->execute();
    $employees = $employeesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $employees = [];
}

// Get unique departments
try {
    $deptStmt = $pdo->prepare("SELECT DISTINCT department FROM attendance WHERE department IS NOT NULL AND department != '' ORDER BY department ASC");
    $deptStmt->execute();
    $departments = $deptStmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $departments = [];
}

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
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" />
        <link href="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/style.min.css" rel="stylesheet" />
        <link href="css/styles.css" rel="stylesheet" />
        <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <style>
            .upload-area { border: 2px dashed #dee2e6; border-radius: 0.5rem; padding: 2rem; text-align: center; background-color: #f8f9fa; cursor: pointer; transition: all 0.3s; }
            .upload-area:hover { border-color: #0d6efd; background-color: #e9ecef; }
            .upload-area i { font-size: 3rem; color: #6c757d; }
            .late-time { color: #dc3545; font-weight: bold; }
            .over-time { color: #28a745; font-weight: bold; }
            .check-in { color: #28a745; font-weight: bold; }
            .check-out { color: #dc3545; font-weight: bold; }
            .status-badge { display: inline-block; padding: 0.25rem 0.5rem; border-radius: 0.25rem; font-weight: 500; font-size: 0.875rem; }
            .status-present { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
            .status-halfday { background-color: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
            .status-absent { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
            .modal-header { background-color: #f8f9fa; border-bottom: 2px solid #dee2e6; }
            .modal-title { color: #333; font-weight: 600; }
            .time-validation-error { border-color: #dc3545 !important; }
            .time-validation-error:focus { border-color: #dc3545 !important; box-shadow: 0 0 0 0.25rem rgba(220, 53, 69, 0.25) !important; }
            .validation-message { font-size: 0.875rem; margin-top: 0.25rem; display: none; }
            .validation-message.show { display: block; }
            .validation-success { border-color: #28a745 !important; }
            .validation-success:focus { border-color: #28a745 !important; box-shadow: 0 0 0 0.25rem rgba(40, 167, 69, 0.25) !important; }
            .action-buttons { white-space: nowrap; display: flex; gap: 4px; flex-wrap: nowrap; }
            .action-buttons .btn { padding: 0.25rem 0.5rem; font-size: 0.875rem; flex: 0 0 auto; }
            .calculation-info { font-size: 0.85rem; margin-top: 0.25rem; color: #6c757d; }
            .status-column { text-align: center; }
            .time-clear-btn { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #dc3545; cursor: pointer; display: none; }
            .time-clear-btn:hover { color: #dc3545; }
            .time-input-wrapper { position: relative; }
            .time-input-wrapper input { padding-right: 30px; }
            .time-input-wrapper input:not(:placeholder-shown) ~ .time-clear-btn { display: block; }
            .missing-employees-list { max-height: 300px; overflow-y: auto; text-align: left; margin-top: 10px; }
            .missing-employees-list table { width: 100%; font-size: 0.9rem; }
            .missing-employees-list th { background-color: #f8f9fa; position: sticky; top: 0; }
            .time-range-validation-message { font-size: 0.8rem; margin-top: 0.25rem; }
        </style>
    </head>
    <body class="sb-nav-fixed">
        <?php include 'includes/top_bar.php';?>
        <div id="layoutSidenav">
            <?php include 'includes/side_menu.php';?>
            <div id="layoutSidenav_content">
                <main>
                    <div class="container-fluid px-4">
                        <h1 class="mt-4">Upload Attendance</h1>
                        <ol class="breadcrumb mb-4">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Upload Attendance</li>
                        </ol>
                        
                        <div class="row">
                            <div class="col-xl-12">
                                <div class="card mb-4">
                                    <div class="card-header"><i class="fas fa-upload me-1"></i> Upload Attendance File</div>
                                    <div class="card-body">
                                        <form method="POST" enctype="multipart/form-data" id="attendanceForm">
                                            <div class="upload-area mb-3" onclick="document.getElementById('attendance_file').click()">
                                                <i class="fas fa-cloud-upload-alt"></i>
                                                <h5 class="mt-3">Click to upload or drag and drop</h5>
                                                <p class="text-muted mb-0">Excel files (.xls, .xlsx) or CSV</p>
                                                <input type="file" id="attendance_file" name="attendance_file" accept=".xls,.xlsx,.csv" style="display: none;" required>
                                            </div>
                                            <div class="d-grid"><button type="submit" class="btn btn-primary"><i class="fas fa-upload me-1"></i> Upload & Process</button></div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-xl-12">
                                <div class="card mb-4">
                                    <div class="card-header"><i class="fas fa-calendar-alt me-1"></i> View Attendance Records</div>
                                    <div class="card-body">
                                        <form method="POST" class="row g-3" id="dateRangeForm">
                                            <div class="col-md-5">
                                                <label for="date_from" class="form-label">From Date</label>
                                                <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo $selected_date_from; ?>" required>
                                            </div>
                                            <div class="col-md-5">
                                                <label for="date_to" class="form-label">To Date</label>
                                                <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo $selected_date_to; ?>" required>
                                            </div>
                                            <div class="col-md-2 d-flex align-items-end">
                                                <button type="submit" name="select_date_range" class="btn btn-primary w-100"><i class="fas fa-search"></i> View</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                                
                                <?php if (!empty($attendance_records)): ?>
                                <div class="card mb-4">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <div><i class="fas fa-clock me-1"></i> Attendance Records (<?php echo count($attendance_records); ?> records)</div>
                                        <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addAttendanceModal"><i class="fas fa-plus me-1"></i> Add Attendance</button>
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
                                                            <td class="check-in"><?php echo $record['check_in'] ? formatTimeTo12Hour($record['check_in']) : '--:--'; ?></td>
                                                            <td><?php echo $record['break_out'] ? formatTimeTo12Hour($record['break_out']) : '--:--'; ?></td>
                                                            <td><?php echo $record['break_in'] ? formatTimeTo12Hour($record['break_in']) : '--:--'; ?></td>
                                                            <td class="check-out"><?php echo $record['check_out'] ? formatTimeTo12Hour($record['check_out']) : '--:--'; ?></td>
                                                            <td class="<?php echo $record['late_time'] > 0 ? 'late-time' : ''; ?>"><?php echo $record['late_time'] > 0 ? $record['late_time'] : '-'; ?></td>
                                                            <td class="<?php echo $record['over_time'] > 0 ? 'over-time' : ''; ?>"><?php echo $record['over_time'] > 0 ? $record['over_time'] : '-'; ?></td>
                                                            <td class="status-column">
                                                                <?php
                                                                $status = $record['status'] ?? 'Present';
                                                                $status_class = 'status-present';
                                                                if ($status == 'Half Day') $status_class = 'status-halfday';
                                                                elseif ($status == 'Absent') $status_class = 'status-absent';
                                                                ?>
                                                                <span class="status-badge <?php echo $status_class; ?>"><?php echo htmlspecialchars($status); ?></span>
                                                            </td>
                                                            <td><?php echo !empty($record['remarks']) ? htmlspecialchars($record['remarks']) : '-'; ?></td>
                                                            <td>
                                                                <div class="action-buttons">
                                                                    <button type="button" class="btn btn-primary btn-sm" onclick='editAttendance(<?php echo json_encode($record, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)' title="Edit"><i class="fas fa-edit"></i></button>
                                                                    <button type="button" class="btn btn-danger btn-sm" onclick="deleteAttendance(<?php echo $record['id']; ?>, '<?php echo htmlspecialchars(addslashes($record['employee_name']), ENT_QUOTES); ?>', '<?php echo $record['attendance_date']; ?>')" title="Delete"><i class="fas fa-trash"></i></button>
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
                                <div class="card mb-4">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <div><i class="fas fa-clock me-1"></i> Attendance Records</div>
                                        <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addAttendanceModal"><i class="fas fa-plus me-1"></i> Add Attendance</button>
                                    </div>
                                    <div class="card-body"><div class="alert alert-info mb-0"><i class="fas fa-info-circle me-2"></i> No attendance records found for the selected date range.</div></div>
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
        <div class="modal fade" id="addAttendanceModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header"><h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i>Add Attendance Record</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <form method="POST" id="addAttendanceForm">
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="modal_employee_id" class="form-label">Employee <span class="text-danger">*</span></label>
                                    <select class="form-select" id="modal_employee_id" name="employee_id" required>
                                        <option value="">Select Employee</option>
                                        <?php foreach ($employees as $emp): ?>
                                            <option value="<?php echo $emp['id']; ?>"><?php echo htmlspecialchars($emp['firstname'] . ' ' . (!empty($emp['middlename']) ? substr($emp['middlename'], 0, 1) . '. ' : '') . $emp['lastname'] . (!empty($emp['suffix']) ? ' ' . $emp['suffix'] : '') . ' (ID: ' . $emp['employee_id'] . ')'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="modal_attendance_date" class="form-label">Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="modal_attendance_date" name="attendance_date" value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label for="modal_department" class="form-label">Department <span class="text-danger">*</span></label>
                                    <select class="form-select" id="modal_department" name="department" required>
                                        <option value="">Select Department</option>
                                        <?php foreach ($departments as $dept): ?>
                                            <option value="<?php echo htmlspecialchars($dept['department']); ?>"><?php echo htmlspecialchars($dept['department']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Late Time (minutes)</label>
                                    <div class="input-group"><input type="number" class="form-control" id="modal_late_time" name="late_time" min="0" value="0" readonly><span class="input-group-text">min</span></div>
                                    <div id="modal_calculation_info" class="calculation-info"></div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="modal_over_time" class="form-label">Overtime (minutes) - Manual Input</label>
                                    <div class="input-group"><input type="number" class="form-control" id="modal_over_time" name="over_time" min="0" value="0"><span class="input-group-text">min</span></div>
                                    <div class="calculation-info"><small class="text-muted">Enter overtime manually in minutes</small></div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="modal_check_in" class="form-label">Check In (00:00-11:59) <small class="text-muted">Late if after 8:15 AM</small></label>
                                    <div class="time-input-wrapper">
                                        <input type="time" class="form-control" id="modal_check_in" name="check_in" step="1">
                                        <button type="button" class="time-clear-btn" onclick="clearTimeField('modal_check_in')"><i class="fas fa-times"></i></button>
                                    </div>
                                    <div id="modal_check_in_range_validation" class="time-range-validation-message text-danger"></div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="modal_break_out" class="form-label">Break Out (12:00-12:29)</label>
                                    <div class="time-input-wrapper">
                                        <input type="time" class="form-control" id="modal_break_out" name="break_out" step="1">
                                        <button type="button" class="time-clear-btn" onclick="clearTimeField('modal_break_out')"><i class="fas fa-times"></i></button>
                                    </div>
                                    <div id="modal_break_out_range_validation" class="time-range-validation-message text-danger"></div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="modal_break_in" class="form-label">Break In (12:30-14:30) <small class="text-muted">Late if after 1:15 PM</small></label>
                                    <div class="time-input-wrapper">
                                        <input type="time" class="form-control" id="modal_break_in" name="break_in" step="1">
                                        <button type="button" class="time-clear-btn" onclick="clearTimeField('modal_break_in')"><i class="fas fa-times"></i></button>
                                    </div>
                                    <div id="modal_break_in_range_validation" class="time-range-validation-message text-danger"></div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="modal_check_out" class="form-label">Check Out (17:00-23:59)</label>
                                    <div class="time-input-wrapper">
                                        <input type="time" class="form-control" id="modal_check_out" name="check_out" step="1">
                                        <button type="button" class="time-clear-btn" onclick="clearTimeField('modal_check_out')"><i class="fas fa-times"></i></button>
                                    </div>
                                    <div id="modal_check_out_range_validation" class="time-range-validation-message text-danger"></div>
                                </div>
                            </div>
                            <div class="row"><div class="col-md-12 mb-3"><label for="modal_remarks" class="form-label">Remarks</label><input type="text" class="form-control" id="modal_remarks" name="remarks" placeholder="Enter remarks"></div></div>
                            <div id="time_validation_summary" class="alert alert-danger small" style="display:none"><i class="fas fa-exclamation-triangle me-2"></i><span id="validation_summary_message"></span></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="add_attendance" class="btn btn-success"><i class="fas fa-save me-1"></i> Save Attendance</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Edit Attendance Modal -->
        <div class="modal fade" id="editAttendanceModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header"><h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Attendance Record</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <form method="POST" id="editAttendanceForm">
                        <input type="hidden" name="attendance_id" id="edit_attendance_id">
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="edit_employee_id" class="form-label">Employee <span class="text-danger">*</span></label>
                                    <select class="form-select" id="edit_employee_id" name="employee_id" required>
                                        <option value="">Select Employee</option>
                                        <?php foreach ($employees as $emp): ?>
                                            <option value="<?php echo $emp['id']; ?>"><?php echo htmlspecialchars($emp['firstname'] . ' ' . (!empty($emp['middlename']) ? substr($emp['middlename'], 0, 1) . '. ' : '') . $emp['lastname'] . (!empty($emp['suffix']) ? ' ' . $emp['suffix'] : '') . ' (ID: ' . $emp['employee_id'] . ')'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="edit_attendance_date" class="form-label">Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="edit_attendance_date" name="attendance_date" required>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label for="edit_department" class="form-label">Department <span class="text-danger">*</span></label>
                                    <select class="form-select" id="edit_department" name="department" required>
                                        <option value="">Select Department</option>
                                        <?php foreach ($departments as $dept): ?>
                                            <option value="<?php echo htmlspecialchars($dept['department']); ?>"><?php echo htmlspecialchars($dept['department']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Late Time (minutes)</label>
                                    <div class="input-group"><input type="number" class="form-control" id="edit_late_time" name="late_time" min="0" value="0" readonly><span class="input-group-text">min</span></div>
                                    <div id="edit_calculation_info" class="calculation-info"></div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="edit_over_time" class="form-label">Overtime (minutes) - Manual Input</label>
                                    <div class="input-group"><input type="number" class="form-control" id="edit_over_time" name="over_time" min="0" value="0"><span class="input-group-text">min</span></div>
                                    <div class="calculation-info"><small class="text-muted">Enter overtime manually in minutes</small></div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="edit_check_in" class="form-label">Check In (00:00-11:59) <small class="text-muted">Late if after 8:15 AM</small></label>
                                    <div class="time-input-wrapper">
                                        <input type="time" class="form-control" id="edit_check_in" name="check_in" step="1">
                                        <button type="button" class="time-clear-btn" onclick="clearTimeField('edit_check_in')"><i class="fas fa-times"></i></button>
                                    </div>
                                    <div id="edit_check_in_range_validation" class="time-range-validation-message text-danger"></div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="edit_break_out" class="form-label">Break Out (12:00-12:29)</label>
                                    <div class="time-input-wrapper">
                                        <input type="time" class="form-control" id="edit_break_out" name="break_out" step="1">
                                        <button type="button" class="time-clear-btn" onclick="clearTimeField('edit_break_out')"><i class="fas fa-times"></i></button>
                                    </div>
                                    <div id="edit_break_out_range_validation" class="time-range-validation-message text-danger"></div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="edit_break_in" class="form-label">Break In (12:30-14:30) <small class="text-muted">Late if after 1:15 PM</small></label>
                                    <div class="time-input-wrapper">
                                        <input type="time" class="form-control" id="edit_break_in" name="break_in" step="1">
                                        <button type="button" class="time-clear-btn" onclick="clearTimeField('edit_break_in')"><i class="fas fa-times"></i></button>
                                    </div>
                                    <div id="edit_break_in_range_validation" class="time-range-validation-message text-danger"></div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="edit_check_out" class="form-label">Check Out (17:00-23:59)</label>
                                    <div class="time-input-wrapper">
                                        <input type="time" class="form-control" id="edit_check_out" name="check_out" step="1">
                                        <button type="button" class="time-clear-btn" onclick="clearTimeField('edit_check_out')"><i class="fas fa-times"></i></button>
                                    </div>
                                    <div id="edit_check_out_range_validation" class="time-range-validation-message text-danger"></div>
                                </div>
                            </div>
                            <div class="row"><div class="col-md-12 mb-3"><label for="edit_remarks" class="form-label">Remarks</label><input type="text" class="form-control" id="edit_remarks" name="remarks" placeholder="Enter remarks"></div></div>
                            <div id="edit_time_validation_summary" class="alert alert-danger small" style="display:none"><i class="fas fa-exclamation-triangle me-2"></i><span id="edit_validation_summary_message"></span></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="edit_attendance" class="btn btn-primary"><i class="fas fa-save me-1"></i> Update Attendance</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <div class="modal fade" id="deleteAttendanceModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-danger text-white"><h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Confirm Delete</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
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
                            <button type="submit" name="delete_attendance" class="btn btn-danger"><i class="fas fa-trash me-1"></i> Delete Record</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>
        <script>
            var missingEmployeesData = <?php echo $missing_employees_json ? $missing_employees_json : 'null'; ?>;
            
            // Standard cutoff times
            var CHECK_IN_CUTOFF = 8 * 60 + 15;   // 08:15 AM - on time up to 8:15, late starting at 8:16
            var BREAK_IN_CUTOFF = 13 * 60 + 15;  // 01:15 PM - on time up to 1:15, late starting at 1:16
            
            function timeToMinutes(timeStr) {
                if (!timeStr || timeStr.trim() === '') return null;
                timeStr = timeStr.trim();
                var parts = timeStr.split(':');
                if (parts.length < 2) return null;
                var hours = parseInt(parts[0], 10);
                var minutes = parseInt(parts[1], 10);
                if (isNaN(hours) || isNaN(minutes)) return null;
                if (hours < 0 || hours > 23 || minutes < 0 || minutes > 59) return null;
                return (hours * 60) + minutes;
            }
            
            function formatTimeTo12Hour(timeStr) {
                if (!timeStr) return '';
                var parts = timeStr.split(':');
                if (parts.length >= 2) {
                    var hours = parseInt(parts[0], 10);
                    var minutes = parts[1];
                    var seconds = parts.length >= 3 ? parts[2] : '00';
                    var ampm = hours >= 12 ? 'PM' : 'AM';
                    hours = hours % 12;
                    hours = hours ? hours : 12;
                    return hours + ':' + minutes + ':' + seconds + ' ' + ampm;
                }
                return timeStr;
            }
            
            function isValidTimeInRange(timeStr, hourMin, minuteMin, hourMax, minuteMax) {
                if (!timeStr || timeStr.trim() === '') return true;
                var parts = timeStr.split(':');
                if (parts.length < 2) return false;
                var hours = parseInt(parts[0], 10);
                var minutes = parseInt(parts[1], 10);
                var totalMinutes = (hours * 60) + minutes;
                return totalMinutes >= (hourMin * 60 + minuteMin) && totalMinutes <= (hourMax * 60 + minuteMax);
            }
            
            function calculateLateMinutes(checkIn, breakIn) {
                var totalLateMinutes = 0;
                var checkInMinutes = timeToMinutes(checkIn);
                var breakInMinutes = timeToMinutes(breakIn);
                
                // Check-in is late if AFTER 8:15 AM
                if (checkInMinutes !== null && checkInMinutes > CHECK_IN_CUTOFF) {
                    totalLateMinutes += (checkInMinutes - CHECK_IN_CUTOFF);
                }
                
                // Break-in is late if AFTER 1:15 PM
                if (breakInMinutes !== null && breakInMinutes > BREAK_IN_CUTOFF) {
                    totalLateMinutes += (breakInMinutes - BREAK_IN_CUTOFF);
                }
                return totalLateMinutes;
            }
            
            function getLateBreakdown(checkIn, breakIn) {
                var checkInMinutes = timeToMinutes(checkIn);
                var breakInMinutes = timeToMinutes(breakIn);
                var checkInLate = 0, breakInLate = 0;
                var checkInReason = '', breakInReason = '';
                
                if (checkInMinutes !== null) {
                    if (checkInMinutes > CHECK_IN_CUTOFF) {
                        checkInLate = checkInMinutes - CHECK_IN_CUTOFF;
                        checkInReason = 'Check In at ' + formatTimeTo12Hour(checkIn) + ' (' + checkInLate + ' min late, after 8:15 AM)';
                    } else {
                        checkInReason = 'Check In at ' + formatTimeTo12Hour(checkIn) + ' (on time, at or before 8:15 AM)';
                    }
                }
                
                if (breakInMinutes !== null) {
                    if (breakInMinutes > BREAK_IN_CUTOFF) {
                        breakInLate = breakInMinutes - BREAK_IN_CUTOFF;
                        breakInReason = 'Break In at ' + formatTimeTo12Hour(breakIn) + ' (' + breakInLate + ' min late, after 1:15 PM)';
                    } else {
                        breakInReason = 'Break In at ' + formatTimeTo12Hour(breakIn) + ' (on time, at or before 1:15 PM)';
                    }
                }
                
                return { total: checkInLate + breakInLate, checkInLate: checkInLate, breakInLate: breakInLate, checkInReason: checkInReason, breakInReason: breakInReason };
            }
            
            function validateTimeRange(prefix) {
                var checkIn = document.getElementById(prefix + 'check_in').value;
                var breakOut = document.getElementById(prefix + 'break_out').value;
                var breakIn = document.getElementById(prefix + 'break_in').value;
                var checkOut = document.getElementById(prefix + 'check_out').value;
                var errorMessages = [];
                
                ['check_in', 'break_out', 'break_in', 'check_out'].forEach(function(name) {
                    var el = document.getElementById(prefix + name);
                    if (el) el.classList.remove('time-validation-error', 'validation-success');
                    var rangeEl = document.getElementById(prefix + name + '_range_validation');
                    if (rangeEl) { rangeEl.innerHTML = ''; rangeEl.style.display = 'none'; }
                });
                
                if (checkIn && !isValidTimeInRange(checkIn, 0, 0, 11, 59)) {
                    document.getElementById(prefix + 'check_in').classList.add('time-validation-error');
                    document.getElementById(prefix + 'check_in_range_validation').innerHTML = '<i class="fas fa-exclamation-circle"></i> Must be between 00:00 and 11:59';
                    document.getElementById(prefix + 'check_in_range_validation').style.display = 'block';
                    errorMessages.push('Check In must be between 00:00 and 11:59.');
                } else if (checkIn) {
                    document.getElementById(prefix + 'check_in').classList.add('validation-success');
                }
                
                if (breakOut && !isValidTimeInRange(breakOut, 12, 0, 12, 29)) {
                    document.getElementById(prefix + 'break_out').classList.add('time-validation-error');
                    document.getElementById(prefix + 'break_out_range_validation').innerHTML = '<i class="fas fa-exclamation-circle"></i> Must be between 12:00 and 12:29';
                    document.getElementById(prefix + 'break_out_range_validation').style.display = 'block';
                    errorMessages.push('Break Out must be between 12:00 and 12:29.');
                } else if (breakOut) {
                    document.getElementById(prefix + 'break_out').classList.add('validation-success');
                }
                
                if (breakIn && !isValidTimeInRange(breakIn, 12, 30, 14, 30)) {
                    document.getElementById(prefix + 'break_in').classList.add('time-validation-error');
                    document.getElementById(prefix + 'break_in_range_validation').innerHTML = '<i class="fas fa-exclamation-circle"></i> Must be between 12:30 and 14:30';
                    document.getElementById(prefix + 'break_in_range_validation').style.display = 'block';
                    errorMessages.push('Break In must be between 12:30 and 14:30.');
                } else if (breakIn) {
                    document.getElementById(prefix + 'break_in').classList.add('validation-success');
                }
                
                if (checkOut && !isValidTimeInRange(checkOut, 17, 0, 23, 59)) {
                    document.getElementById(prefix + 'check_out').classList.add('time-validation-error');
                    document.getElementById(prefix + 'check_out_range_validation').innerHTML = '<i class="fas fa-exclamation-circle"></i> Must be between 17:00 and 23:59';
                    document.getElementById(prefix + 'check_out_range_validation').style.display = 'block';
                    errorMessages.push('Check Out must be between 17:00 and 23:59.');
                } else if (checkOut) {
                    document.getElementById(prefix + 'check_out').classList.add('validation-success');
                }
                
                return { isValid: errorMessages.length === 0, messages: errorMessages };
            }
            
            function updateTimeDisplays(prefix) {
                var checkIn = document.getElementById(prefix + 'check_in').value;
                var breakIn = document.getElementById(prefix + 'break_in').value;
                var lateTimeInput = document.getElementById(prefix + 'late_time');
                var calculationInfo = document.getElementById(prefix + 'calculation_info');
                
                var lateBreakdown = getLateBreakdown(checkIn, breakIn);
                lateTimeInput.value = lateBreakdown.total;
                
                var lateInfoHtml = '';
                if (checkIn || breakIn) {
                    lateInfoHtml = '<strong>Late Calculation:</strong><br>';
                    if (checkIn) lateInfoHtml += '\u2022 ' + lateBreakdown.checkInReason + '<br>';
                    if (breakIn) lateInfoHtml += '\u2022 ' + lateBreakdown.breakInReason + '<br>';
                }
                calculationInfo.innerHTML = lateInfoHtml;
            }
            
            function validateTimeSequence(prefix) {
                var rangeValidation = validateTimeRange(prefix);
                updateTimeDisplays(prefix);
                var summaryEl = document.getElementById(prefix + 'time_validation_summary');
                if (summaryEl) summaryEl.style.display = 'none';
                if (!rangeValidation.isValid) {
                    if (summaryEl) {
                        document.getElementById(prefix + 'validation_summary_message').innerHTML = rangeValidation.messages.join('<br>');
                        summaryEl.style.display = 'block';
                    }
                    return false;
                }
                return true;
            }
            
            function clearTimeField(fieldId) {
                var field = document.getElementById(fieldId);
                if (field) {
                    field.value = '';
                    field.dispatchEvent(new Event('change'));
                    field.dispatchEvent(new Event('input'));
                    var wrapper = field.closest('.time-input-wrapper');
                    if (wrapper) {
                        var clearBtn = wrapper.querySelector('.time-clear-btn');
                        if (clearBtn) clearBtn.style.display = 'none';
                    }
                }
            }
            
            function editAttendance(record) {
                if (!record) { Swal.fire({ icon: 'error', title: 'Error', text: 'Invalid record data.', timer: 3000 }); return; }
                try {
                    var recordData = typeof record === 'string' ? JSON.parse(record) : record;
                    document.getElementById('edit_attendance_id').value = recordData.id || '';
                    document.getElementById('edit_employee_id').value = recordData.employee_id || '';
                    document.getElementById('edit_attendance_date').value = recordData.attendance_date || '';
                    document.getElementById('edit_department').value = recordData.department || '';
                    document.getElementById('edit_check_in').value = recordData.check_in || '';
                    document.getElementById('edit_break_out').value = recordData.break_out || '';
                    document.getElementById('edit_break_in').value = recordData.break_in || '';
                    document.getElementById('edit_check_out').value = recordData.check_out || '';
                    document.getElementById('edit_over_time').value = recordData.over_time || 0;
                    document.getElementById('edit_remarks').value = recordData.remarks || '';
                    
                    document.querySelectorAll('#editAttendanceModal .time-input-wrapper input[type="time"]').forEach(function(input) {
                        var wrapper = input.closest('.time-input-wrapper');
                        wrapper.querySelector('.time-clear-btn').style.display = input.value ? 'block' : 'none';
                    });
                    
                    updateTimeDisplays('edit_');
                    ['edit_check_in_range_validation', 'edit_break_out_range_validation', 'edit_break_in_range_validation', 'edit_check_out_range_validation'].forEach(function(id) {
                        var el = document.getElementById(id); if (el) { el.innerHTML = ''; el.style.display = 'none'; }
                    });
                    
                    new bootstrap.Modal(document.getElementById('editAttendanceModal')).show();
                } catch (error) {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to load record data.', timer: 3000 });
                }
            }
            
            function deleteAttendance(id, employeeName, date) {
                document.getElementById('delete_attendance_id').value = id;
                document.getElementById('delete_employee_name').textContent = employeeName || '';
                document.getElementById('delete_attendance_date').textContent = date || '';
                new bootstrap.Modal(document.getElementById('deleteAttendanceModal')).show();
            }
            
            function escapeHtml(str) {
                if (!str) return '';
                var div = document.createElement('div');
                div.appendChild(document.createTextNode(str));
                return div.innerHTML;
            }
            
            document.addEventListener('DOMContentLoaded', function() {
                <?php if ($message): ?>
                Swal.fire({ icon: '<?php echo $message_type; ?>', title: '<?php echo $message_type == 'success' ? 'Success!' : 'Error!'; ?>', html: '<?php echo str_replace(["\r\n", "\n", "\r"], '<br>', addslashes($message)); ?>', timer: <?php echo $message_type == 'success' ? 3000 : 5000; ?>, timerProgressBar: true, showConfirmButton: true });
                <?php endif; ?>
                
                if (missingEmployeesData) {
                    var empList = missingEmployeesData.employees;
                    var successCount = missingEmployeesData.success_count;
                    var missingCount = missingEmployeesData.missing_count;
                    var missingHtml = '<div style="margin-bottom:10px"><strong>Saved: ' + successCount + '</strong><br><strong style="color:#dc3545">Missing: ' + missingCount + '</strong></div>';
                    missingHtml += '<div class="missing-employees-list"><table class="table table-sm table-bordered"><thead><tr><th>ID</th><th>Name</th><th>Date</th></tr></thead><tbody>';
                    for (var i = 0; i < empList.length; i++) {
                        missingHtml += '<tr><td>' + escapeHtml(empList[i].employee_id) + '</td><td>' + escapeHtml(empList[i].name) + '</td><td>' + escapeHtml(empList[i].date) + '</td></tr>';
                    }
                    missingHtml += '</tbody></table></div>';
                    Swal.fire({ icon: 'warning', title: 'Some Employees Not Found', html: missingHtml, width: '600px', confirmButtonText: 'OK' });
                }
                
                <?php if (isset($attendance_error)): ?>
                Swal.fire({ icon: 'error', title: 'Database Error', text: '<?php echo addslashes($attendance_error); ?>', timer: 5000 });
                <?php endif; ?>
                
                <?php if (!isset($attendance_error) && empty($attendance_records) && !$message): ?>
                Swal.fire({ icon: 'info', title: 'No Records', text: 'No attendance records for this date range.', timer: 3000 });
                <?php endif; ?>
                
                if (document.getElementById('attendanceRecordsTable')) {
                    new simpleDatatables.DataTable("#attendanceRecordsTable", { searchable: true, fixedHeight: false, perPage: 10 });
                }
                
                ['modal', 'edit'].forEach(function(prefix) {
                    ['check_in', 'break_out', 'break_in', 'check_out'].forEach(function(name) {
                        var field = document.getElementById(prefix + '_' + name);
                        if (field) {
                            field.addEventListener('change', function() { validateTimeSequence(prefix + '_'); });
                            field.addEventListener('input', function() {
                                validateTimeSequence(prefix + '_');
                                var wrapper = this.closest('.time-input-wrapper');
                                if (wrapper) { var btn = wrapper.querySelector('.time-clear-btn'); if (btn) btn.style.display = this.value ? 'block' : 'none'; }
                            });
                        }
                    });
                });
                
                document.querySelectorAll('.time-input-wrapper input[type="time"]').forEach(function(input) {
                    var wrapper = input.closest('.time-input-wrapper');
                    if (wrapper) { var btn = wrapper.querySelector('.time-clear-btn'); if (btn) btn.style.display = input.value ? 'block' : 'none'; }
                });
            });
            
            // File upload
            document.getElementById('attendance_file')?.addEventListener('change', function() {
                if (this.files[0]) document.querySelector('.upload-area h5').textContent = this.files[0].name;
            });
            
            var uploadArea = document.querySelector('.upload-area');
            if (uploadArea) {
                uploadArea.addEventListener('dragover', function(e) { e.preventDefault(); this.style.borderColor = '#0d6efd'; });
                uploadArea.addEventListener('dragleave', function() { this.style.borderColor = '#dee2e6'; });
                uploadArea.addEventListener('drop', function(e) {
                    e.preventDefault(); this.style.borderColor = '#dee2e6';
                    if (e.dataTransfer.files[0]) {
                        document.getElementById('attendance_file').files = e.dataTransfer.files;
                        document.querySelector('.upload-area h5').textContent = e.dataTransfer.files[0].name;
                    }
                });
            }
            
            document.getElementById('attendanceForm')?.addEventListener('submit', function(e) {
                if (!document.getElementById('attendance_file').files.length) {
                    e.preventDefault(); Swal.fire({ icon: 'warning', title: 'No File', text: 'Please select a file.', timer: 2000 });
                }
            });
            
            document.getElementById('dateRangeForm')?.addEventListener('submit', function(e) {
                if (document.getElementById('date_from').value > document.getElementById('date_to').value) {
                    e.preventDefault(); Swal.fire({ icon: 'error', title: 'Invalid Range', text: 'From date cannot be later than To date.', timer: 3000 });
                }
            });
            
            document.getElementById('addAttendanceForm')?.addEventListener('submit', function(e) {
                if (!document.getElementById('modal_employee_id').value || !document.getElementById('modal_department').value) {
                    e.preventDefault(); Swal.fire({ icon: 'warning', title: 'Required', text: 'Fill all required fields.', timer: 3000 }); return;
                }
                if (!validateTimeSequence('modal_')) {
                    e.preventDefault(); Swal.fire({ icon: 'error', title: 'Invalid Time', html: document.getElementById('validation_summary_message').innerHTML, timer: 4000 }); return;
                }
            });
            
            document.getElementById('editAttendanceForm')?.addEventListener('submit', function(e) {
                if (!document.getElementById('edit_employee_id').value || !document.getElementById('edit_department').value) {
                    e.preventDefault(); Swal.fire({ icon: 'warning', title: 'Required', text: 'Fill all required fields.', timer: 3000 }); return;
                }
                if (!validateTimeSequence('edit_')) {
                    e.preventDefault(); Swal.fire({ icon: 'error', title: 'Invalid Time', html: document.getElementById('edit_validation_summary_message').innerHTML, timer: 4000 }); return;
                }
            });
            
            document.getElementById('addAttendanceModal')?.addEventListener('hidden.bs.modal', function() {
                document.getElementById('addAttendanceForm').reset();
                document.getElementById('modal_attendance_date').value = '<?php echo date('Y-m-d'); ?>';
                document.getElementById('modal_late_time').value = '0';
                document.getElementById('modal_over_time').value = '0';
                ['modal_calculation_info', 'modal_check_in_range_validation', 'modal_break_out_range_validation', 'modal_break_in_range_validation', 'modal_check_out_range_validation'].forEach(function(id) {
                    var el = document.getElementById(id); if (el) el.innerHTML = '';
                });
                document.getElementById('time_validation_summary').style.display = 'none';
                document.querySelectorAll('#addAttendanceModal .time-clear-btn').forEach(function(btn) { btn.style.display = 'none'; });
            });
            
            document.getElementById('editAttendanceModal')?.addEventListener('hidden.bs.modal', function() {
                document.getElementById('edit_late_time').value = '0';
                document.getElementById('edit_over_time').value = '0';
                ['edit_calculation_info', 'edit_check_in_range_validation', 'edit_break_out_range_validation', 'edit_break_in_range_validation', 'edit_check_out_range_validation'].forEach(function(id) {
                    var el = document.getElementById(id); if (el) el.innerHTML = '';
                });
                document.getElementById('edit_time_validation_summary').style.display = 'none';
                document.querySelectorAll('#editAttendanceModal .time-clear-btn').forEach(function(btn) { btn.style.display = 'none'; });
            });
        </script>
    </body>
</html>