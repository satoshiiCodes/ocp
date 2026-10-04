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
$selected_date_from = '';
$selected_date_to = '';
$selected_department = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Handle date range and department selection for viewing
    if (isset($_POST['apply_filter'])) {
        $selected_date_from = $_POST['date_from'];
        $selected_date_to = $_POST['date_to'];
        $selected_department = $_POST['department_filter'];
    }
    
    // Handle clear filter
    if (isset($_POST['clear_filter'])) {
        $selected_date_from = '';
        $selected_date_to = '';
        $selected_department = '';
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
        $late_time = (!empty($_POST['late_time']) && $_POST['late_time'] !== '') ? $_POST['late_time'] : 0;
        $remarks = $_POST['remarks'];
        
        // Calculate overtime based on check-in (before 7:00 AM) and check-out (after 6:00 PM)
        $over_time = calculateOvertime($check_in, $check_out);
        
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
        $late_time = (!empty($_POST['late_time']) && $_POST['late_time'] !== '') ? $_POST['late_time'] : 0;
        $remarks = $_POST['remarks'];
        
        // Calculate overtime based on check-in (before 7:00 AM) and check-out (after 6:00 PM)
        $over_time = calculateOvertime($check_in, $check_out);
        
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
                // Save to database
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
 * Calculate overtime minutes based on check-in (before 7:00 AM) and check-out (after 6:00 PM)
 * 
 * @param string|null $check_in
 * @param string|null $check_out
 * @return int Total overtime minutes
 */
function calculateOvertime($check_in, $check_out) {
    $total_overtime = 0;
    
    // Standard times in minutes since midnight
    $STANDARD_START = 7 * 60; // 07:00
    $STANDARD_END = 18 * 60; // 18:00 (6:00 PM)
    
    // Calculate overtime from early check-in (before 7:00 AM)
    if (!empty($check_in)) {
        $checkInMinutes = timeToMinutes($check_in);
        if ($checkInMinutes !== null && $checkInMinutes < $STANDARD_START) {
            $total_overtime += ($STANDARD_START - $checkInMinutes);
        }
    }
    
    // Calculate overtime from late check-out (after 6:00 PM)
    // Only count if check-out is 6:00 PM (18:00) or later
    if (!empty($check_out)) {
        $checkOutMinutes = timeToMinutes($check_out);
        if ($checkOutMinutes !== null && $checkOutMinutes >= $STANDARD_END) {
            $total_overtime += ($checkOutMinutes - $STANDARD_END);
        }
    }
    
    return $total_overtime;
}

/**
 * Convert time string to minutes since midnight
 * 
 * @param string $timeStr Time in HH:MM or HH:MM:SS format
 * @return int|null Minutes since midnight or null if invalid
 */
function timeToMinutes($timeStr) {
    if (empty($timeStr)) {
        return null;
    }
    
    $parts = explode(':', $timeStr);
    if (count($parts) < 2) {
        return null;
    }
    
    $hours = (int)$parts[0];
    $minutes = (int)$parts[1];
    
    return ($hours * 60) + $minutes;
}

/**
 * Format time to 12-hour format with AM/PM
 * 
 * @param string $timeStr Time in 24-hour format (HH:MM:SS or HH:MM)
 * @return string Formatted time (e.g., "8:00:56 AM" or "7:00:37 PM")
 */
function formatTimeTo12Hour($timeStr) {
    if (empty($timeStr)) {
        return '';
    }
    
    // Remove seconds if present and add them back with proper formatting
    $parts = explode(':', $timeStr);
    if (count($parts) >= 2) {
        $hours = (int)$parts[0];
        $minutes = $parts[1];
        $seconds = isset($parts[2]) ? $parts[2] : '00';
        
        $ampm = $hours >= 12 ? 'PM' : 'AM';
        $hours12 = $hours % 12;
        $hours12 = $hours12 ? $hours12 : 12; // Convert 0 to 12
        
        return sprintf("%d:%s:%s %s", $hours12, $minutes, $seconds, $ampm);
    }
    
    return $timeStr;
}

/**
 * Calculate attendance status based on time entries
 * 
 * @param string|null $check_in
 * @param string|null $break_out
 * @param string|null $break_in
 * @param string|null $check_out
 * @return string 'Present', 'Half Day', or 'Absent'
 */
function calculateAttendanceStatus($check_in, $break_out, $break_in, $check_out) {
    $has_check_in = !empty($check_in);
    $has_break_out = !empty($break_out);
    $has_break_in = !empty($break_in);
    $has_check_out = !empty($check_out);
    
    // Count how many times are present
    $time_count = 0;
    if ($has_check_in) $time_count++;
    if ($has_break_out) $time_count++;
    if ($has_break_in) $time_count++;
    if ($has_check_out) $time_count++;
    
    // Case 1: No times at all - Absent
    if ($time_count === 0) {
        return 'Absent';
    }
    
    // Case 2: Only one time present - Absent
    if ($time_count === 1) {
        return 'Absent';
    }
    
    // Case 3: Have all 4 times - Present
    if ($has_check_in && $has_break_out && $has_break_in && $has_check_out) {
        return 'Present';
    }
    
    // Case 4: Have check-in and check-out only (no break times) - Present
    if ($has_check_in && $has_check_out && !$has_break_out && !$has_break_in) {
        return 'Present';
    }
    
    // Case 5: Have check-in and break-out only (no break-in and check-out) - Half Day
    if ($has_check_in && $has_break_out && !$has_break_in && !$has_check_out) {
        return 'Half Day';
    }
    
    // Case 6: Have break-in and check-out only (no check-in and break-out) - Half Day
    if (!$has_check_in && !$has_break_out && $has_break_in && $has_check_out) {
        return 'Half Day';
    }
    
    // Case 7: Have check-in and break-in only (missing break-out and check-out) - Half Day
    if ($has_check_in && !$has_break_out && $has_break_in && !$has_check_out) {
        return 'Half Day';
    }
    
    // Case 8: Have check-in, break-out, break-in (missing check-out) - Half Day
    if ($has_check_in && $has_break_out && $has_break_in && !$has_check_out) {
        return 'Half Day';
    }
    
    // Case 9: Have break-out, break-in, check-out (missing check-in) - Half Day
    if (!$has_check_in && $has_break_out && $has_break_in && $has_check_out) {
        return 'Half Day';
    }
    
    // Case 10: Any other combination of 2 or 3 times that doesn't fit above patterns
    // If we have check-in with any other single time (except check-out only which is case 4)
    if ($has_check_in && $time_count === 2) {
        return 'Half Day';
    }
    
    // If we have check-out with any other single time (except check-in which is case 4)
    if ($has_check_out && $time_count === 2 && !$has_check_in) {
        return 'Half Day';
    }
    
    // Default to Present if we can't determine (shouldn't reach here)
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
        // Find the header row (row with Employee ID, Name, Department, etc.)
        $header_row_index = -1;
        $header_columns = [];
        
        foreach ($rows as $index => $row) {
            // Clean the row data - trim spaces and convert to string
            $cleaned_row = array_map(function($cell) {
                return trim(strval($cell));
            }, $row);
            
            if (isset($cleaned_row[0]) && $cleaned_row[0] == 'Employee ID') {
                $header_row_index = $index;
                $header_columns = $cleaned_row;
                break;
            }
        }
        
        if ($header_row_index == -1) {
            $result['message'] = 'Could not find header row (Employee ID, Name, Department, etc.).';
            return $result;
        }
        
        // Get the column indices
        $col_indices = [
            'employee_id' => array_search('Employee ID', $header_columns),
            'name' => array_search('Name', $header_columns),
            'department' => array_search('Department', $header_columns),
            'date' => array_search('Date', $header_columns),
            'late_time' => array_search('Late Time(min)', $header_columns),
            'remarks' => array_search('Remarks', $header_columns)
        ];
        
        // Validate required columns
        if ($col_indices['employee_id'] === false || $col_indices['name'] === false || 
            $col_indices['date'] === false) {
            $result['message'] = 'Required columns not found. Expected: Employee ID, Name, Date';
            return $result;
        }
        
        // The actual data rows start after the header row and the sub-header row
        $data_start_row = $header_row_index + 2; // Skip the sub-header row (On/Off row)
        
        // Parse each data row
        for ($i = $data_start_row; $i < count($rows); $i++) {
            $row = $rows[$i];
            
            // Clean the row data
            $cleaned_row = array_map(function($cell) {
                return trim(strval($cell));
            }, $row);
            
            // Skip empty rows
            if (empty(array_filter($cleaned_row))) {
                continue;
            }
            
            // Skip rows that don't have at least an employee ID
            if (!isset($cleaned_row[0]) || trim($cleaned_row[0]) == '') {
                continue;
            }
            
            $employee_id = trim($cleaned_row[0]);
            
            // Skip if employee ID is not numeric (metadata rows)
            if (!is_numeric($employee_id) && $employee_id != '') {
                continue;
            }
            
            // Parse date
            $date = isset($cleaned_row[$col_indices['date']]) ? trim($cleaned_row[$col_indices['date']]) : '';
            
            // Handle Excel date (if it's a numeric value)
            if (is_numeric($date) && $date > 40000) {
                // Convert Excel serial date to PHP date
                try {
                    $date = Date::excelToDateTimeObject($date)->format('Y-m-d');
                } catch (Exception $e) {
                    // If conversion fails, try to handle as string
                }
            }
            
            // Convert date format if needed (from YYYY/MM/DD to YYYY-MM-DD)
            if (strpos($date, '/') !== false) {
                $date_parts = explode('/', $date);
                if (count($date_parts) == 3) {
                    $date = $date_parts[0] . '-' . $date_parts[1] . '-' . $date_parts[2];
                }
            }
            
            // Parse time values - handle potential Excel time values
            $check_in = isset($cleaned_row[4]) ? trim($cleaned_row[4]) : '';
            $break_out = isset($cleaned_row[5]) ? trim($cleaned_row[5]) : '';
            $break_in = isset($cleaned_row[6]) ? trim($cleaned_row[6]) : '';
            $check_out = isset($cleaned_row[7]) ? trim($cleaned_row[7]) : '';
            $late_time = isset($cleaned_row[$col_indices['late_time']]) ? trim($cleaned_row[$col_indices['late_time']]) : 0;
            $remarks = isset($cleaned_row[$col_indices['remarks']]) ? trim($cleaned_row[$col_indices['remarks']]) : '';
            
            // Handle Excel time values (decimal numbers) and convert to proper format
            $check_in = convertExcelTime($check_in);
            $break_out = convertExcelTime($break_out);
            $break_in = convertExcelTime($break_in);
            $check_out = convertExcelTime($check_out);
            
            // Convert '--:--' or '00:00:00' or empty to null for database
            $check_in = (empty($check_in) || $check_in == '--:--' || $check_in == '00:00:00') ? null : $check_in;
            $break_out = (empty($break_out) || $break_out == '--:--' || $break_out == '00:00:00') ? null : $break_out;
            $break_in = (empty($break_in) || $break_in == '--:--' || $break_in == '00:00:00') ? null : $break_in;
            $check_out = (empty($check_out) || $check_out == '--:--' || $check_out == '00:00:00') ? null : $check_out;
            
            // Handle late_time - if empty or non-numeric, set to 0
            if (empty($late_time) || !is_numeric($late_time)) {
                $late_time = 0;
            }
            
            // Calculate overtime based on check-in and check-out times
            $over_time = calculateOvertime($check_in, $check_out);
            
            $result['data'][] = [
                'employee_id' => $employee_id,
                'name' => isset($cleaned_row[$col_indices['name']]) ? trim($cleaned_row[$col_indices['name']]) : '',
                'department' => isset($cleaned_row[$col_indices['department']]) ? trim($cleaned_row[$col_indices['department']]) : '',
                'date' => $date,
                'check_in' => $check_in,
                'break_out' => $break_out,
                'break_in' => $break_in,
                'check_out' => $check_out,
                'late_time' => $late_time,
                'over_time' => $over_time,
                'remarks' => $remarks
            ];
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
    // If value is empty, return null
    if (empty($value) || $value === null || $value === '') {
        return null;
    }
    
    // If it's '--:--', return null
    if ($value == '--:--') {
        return null;
    }
    
    // If it's already in time format (HH:MM or HH:MM:SS), validate it
    if (preg_match('/^([0-1][0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/', $value)) {
        // Check if it's exactly 00:00:00 or 00:00
        if ($value == '00:00:00' || $value == '00:00') {
            return null;
        }
        return $value;
    }
    
    // Handle Excel time (decimal) - numbers between 0 and 1
    if (is_numeric($value) && $value > 0 && $value < 1) {
        $total_seconds = round($value * 24 * 60 * 60);
        
        // If it's essentially 0 seconds, return null
        if ($total_seconds <= 0) {
            return null;
        }
        
        $hours = floor($total_seconds / 3600);
        $minutes = floor(($total_seconds % 3600) / 60);
        $seconds = $total_seconds % 60;
        
        // If hours and minutes are both 0, it's midnight - return null
        if ($hours == 0 && $minutes == 0) {
            return null;
        }
        
        return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    }
    
    // If it's a string '0', return null
    if ($value === '0' || $value === 0) {
        return null;
    }
    
    return $value;
}

/**
 * Format employee name as "Firstname M. Lastname Suffix"
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
        'data' => []
    ];
    
    try {
        $pdo->beginTransaction();
        
        // First, get all employees to map names to IDs
        $employeesStmt = $pdo->prepare("SELECT id, employee_id, firstname, middlename, lastname, suffix FROM employee WHERE status = 'active'");
        $employeesStmt->execute();
        $employees = $employeesStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Create lookup arrays
        $employee_by_code = [];
        $employee_by_formatted_name = [];
        foreach ($employees as $emp) {
            $employee_by_code[$emp['employee_id']] = $emp;
            // Store both raw and formatted versions for matching
            $formatted_name = formatEmployeeName($emp['firstname'], $emp['middlename'], $emp['lastname'], $emp['suffix']);
            $employee_by_formatted_name[strtolower($formatted_name)] = $emp;
            
            // Also store simple firstname + lastname for matching
            $simple_name = strtolower($emp['firstname'] . ' ' . $emp['lastname']);
            $employee_by_formatted_name[$simple_name] = $emp;
        }
        
        $success_count = 0;
        $error_count = 0;
        $errors = [];
        $saved_data = [];
        
        foreach ($data as $record) {
            // Skip if date is invalid
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $record['date'])) {
                $error_count++;
                $errors[] = "Invalid date format for employee {$record['name']}: {$record['date']}";
                continue;
            }
            
            // Try to find employee by ID first
            $employee = null;
            if (isset($employee_by_code[$record['employee_id']])) {
                $employee = $employee_by_code[$record['employee_id']];
            } else {
                // Try to find by formatted name
                $name_lower = strtolower(trim($record['name']));
                
                // Try exact match first
                if (isset($employee_by_formatted_name[$name_lower])) {
                    $employee = $employee_by_formatted_name[$name_lower];
                } else {
                    // Try partial name match
                    foreach ($employees as $emp) {
                        // Check formatted name
                        $formatted_name = strtolower(formatEmployeeName($emp['firstname'], $emp['middlename'], $emp['lastname'], $emp['suffix']));
                        
                        // Check simple name
                        $simple_name = strtolower($emp['firstname'] . ' ' . $emp['lastname']);
                        
                        // Check if the uploaded name contains the employee's formatted name or vice versa
                        if (strpos($formatted_name, $name_lower) !== false || 
                            strpos($name_lower, $formatted_name) !== false ||
                            strpos($simple_name, $name_lower) !== false || 
                            strpos($name_lower, $simple_name) !== false) {
                            $employee = $emp;
                            break;
                        }
                        
                        // Check if it matches firstname + lastname without middlename
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
                $error_count++;
                $errors[] = "Employee not found: ID={$record['employee_id']}, Name={$record['name']}";
                continue;
            }
            
            // Format the employee name properly
            $formatted_employee_name = formatEmployeeName(
                $employee['firstname'], 
                $employee['middlename'], 
                $employee['lastname'], 
                $employee['suffix']
            );
            
            // Calculate status based on attendance times
            $status = calculateAttendanceStatus(
                $record['check_in'], 
                $record['break_out'], 
                $record['break_in'], 
                $record['check_out']
            );
            
            // Check if record already exists
            $checkStmt = $pdo->prepare("
                SELECT id FROM attendance 
                WHERE employee_id = :employee_id AND attendance_date = :attendance_date
            ");
            $checkStmt->bindParam(':employee_id', $employee['id']);
            $checkStmt->bindParam(':attendance_date', $record['date']);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() > 0) {
                // Update existing record
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
                $updateStmt->bindParam(':check_in', $record['check_in']);
                $updateStmt->bindParam(':break_out', $record['break_out']);
                $updateStmt->bindParam(':break_in', $record['break_in']);
                $updateStmt->bindParam(':check_out', $record['check_out']);
                $updateStmt->bindParam(':late_time', $record['late_time']);
                $updateStmt->bindParam(':over_time', $record['over_time']);
                $updateStmt->bindParam(':status', $status);
                $updateStmt->bindParam(':remarks', $record['remarks']);
                $updateStmt->bindParam(':updated_by', $user_id);
                $updateStmt->bindParam(':employee_id', $employee['id']);
                $updateStmt->bindParam(':attendance_date', $record['date']);
                
                if ($updateStmt->execute()) {
                    $success_count++;
                    $record['db_status'] = 'Updated';
                    $record['employee_db_id'] = $employee['id'];
                    $record['formatted_name'] = $formatted_employee_name;
                    $record['status'] = $status;
                    $saved_data[] = $record;
                } else {
                    $error_count++;
                    $errors[] = "Failed to update record for {$formatted_employee_name} on {$record['date']}";
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
                $insertStmt->bindParam(':employee_name', $formatted_employee_name);
                $insertStmt->bindParam(':department', $record['department']);
                $insertStmt->bindParam(':attendance_date', $record['date']);
                $insertStmt->bindParam(':check_in', $record['check_in']);
                $insertStmt->bindParam(':break_out', $record['break_out']);
                $insertStmt->bindParam(':break_in', $record['break_in']);
                $insertStmt->bindParam(':check_out', $record['check_out']);
                $insertStmt->bindParam(':late_time', $record['late_time']);
                $insertStmt->bindParam(':over_time', $record['over_time']);
                $insertStmt->bindParam(':status', $status);
                $insertStmt->bindParam(':remarks', $record['remarks']);
                $insertStmt->bindParam(':created_by', $user_id);
                
                if ($insertStmt->execute()) {
                    $success_count++;
                    $record['db_status'] = 'Inserted';
                    $record['employee_db_id'] = $employee['id'];
                    $record['formatted_name'] = $formatted_employee_name;
                    $record['status'] = $status;
                    $saved_data[] = $record;
                } else {
                    $error_count++;
                    $error_info = $insertStmt->errorInfo();
                    $errors[] = "Failed to insert record for {$formatted_employee_name} on {$record['date']}: " . $error_info[2];
                }
            }
        }
        
        if ($error_count == 0) {
            $pdo->commit();
            $result['success'] = true;
            $result['message'] = "Successfully saved $success_count attendance records.";
            $result['data'] = $saved_data;
        } else {
            $pdo->rollBack();
            $result['message'] = "Processed with errors. Success: $success_count, Errors: $error_count\n" . implode("\n", array_slice($errors, 0, 5));
            if (count($errors) > 5) {
                $result['message'] .= "\n... and " . (count($errors) - 5) . " more errors.";
            }
        }
        
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $result['message'] = 'Database error: ' . $e->getMessage();
    }
    
    return $result;
}

// Fetch attendance records with filters
$attendance_records = [];
$attendance_error = null;

try {
    $sql = "
        SELECT a.*, 
               e.employee_id as emp_code
        FROM attendance a
        JOIN employee e ON a.employee_id = e.id
        WHERE 1=1
    ";
    $params = [];
    
    // Apply date range filter
    if (!empty($selected_date_from) && !empty($selected_date_to)) {
        $sql .= " AND a.attendance_date BETWEEN :date_from AND :date_to";
        $params[':date_from'] = $selected_date_from;
        $params[':date_to'] = $selected_date_to;
    }
    
    // Apply department filter
    if (!empty($selected_department)) {
        $sql .= " AND a.department = :department";
        $params[':department'] = $selected_department;
    }
    
    $sql .= " ORDER BY a.attendance_date DESC, a.employee_name ASC";
    
    $attendanceStmt = $pdo->prepare($sql);
    
    foreach ($params as $key => $value) {
        $attendanceStmt->bindValue($key, $value);
    }
    
    $attendanceStmt->execute();
    $attendance_records = $attendanceStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
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

// Get unique departments from attendance table for dropdown
try {
    $deptStmt = $pdo->prepare("SELECT DISTINCT department FROM attendance WHERE department IS NOT NULL AND department != '' ORDER BY department ASC");
    $deptStmt->execute();
    $departments = $deptStmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $departments = [];
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>Payroll - OCP Construction</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" />
        <link href="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/style.min.css" rel="stylesheet" />
        <link href="css/styles.css" rel="stylesheet" />
        <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
        <!-- SweetAlert2 CSS and JS -->
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <style>
            .upload-area {
                border: 2px dashed #dee2e6;
                border-radius: 0.5rem;
                padding: 2rem;
                text-align: center;
                background-color: #f8f9fa;
                cursor: pointer;
                transition: all 0.3s;
            }
            .upload-area:hover {
                border-color: #0d6efd;
                background-color: #e9ecef;
            }
            .upload-area i {
                font-size: 3rem;
                color: #6c757d;
            }
            .time-badge {
                font-size: 0.8rem;
                padding: 0.2rem 0.4rem;
                margin: 0.1rem;
                display: inline-block;
            }
            .late-time {
                color: #dc3545;
                font-weight: bold;
            }
            .over-time {
                color: #28a745;
                font-weight: bold;
            }
            .check-in {
                color: #28a745;
                font-weight: bold;
            }
            .check-out {
                color: #dc3545;
                font-weight: bold;
            }
            .status-badge {
                display: inline-block;
                padding: 0.25rem 0.5rem;
                border-radius: 0.25rem;
                font-weight: 500;
                font-size: 0.875rem;
            }
            .status-present {
                background-color: #d4edda;
                color: #155724;
                border: 1px solid #c3e6cb;
            }
            .status-halfday {
                background-color: #fff3cd;
                color: #856404;
                border: 1px solid #ffeeba;
            }
            .status-absent {
                background-color: #f8d7da;
                color: #721c24;
                border: 1px solid #f5c6cb;
            }
            /* Modal styles */
            .modal-header {
                background-color: #f8f9fa;
                border-bottom: 2px solid #dee2e6;
            }
            .modal-title {
                color: #333;
                font-weight: 600;
            }
            .time-input-group {
                margin-bottom: 1rem;
            }
            .time-input-group label {
                font-weight: 500;
                margin-bottom: 0.25rem;
                color: #555;
            }
            .time-input-group input {
                border: 1px solid #ced4da;
                border-radius: 0.375rem;
                padding: 0.375rem 0.75rem;
                width: 100%;
            }
            .time-input-group input:focus {
                border-color: #86b7fe;
                outline: 0;
                box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
            }
            .btn-add-attendance {
                margin-bottom: 1rem;
            }
            .time-validation-error {
                border-color: #dc3545 !important;
            }
            .time-validation-error:focus {
                border-color: #dc3545 !important;
                box-shadow: 0 0 0 0.25rem rgba(220, 53, 69, 0.25) !important;
            }
            .validation-message {
                font-size: 0.875rem;
                margin-top: 0.25rem;
                display: none;
            }
            .validation-message.show {
                display: block;
            }
            .validation-success {
                border-color: #28a745 !important;
            }
            .validation-success:focus {
                border-color: #28a745 !important;
                box-shadow: 0 0 0 0.25rem rgba(40, 167, 69, 0.25) !important;
            }
            .action-buttons {
                white-space: nowrap;
                display: flex;
                gap: 4px;
                flex-wrap: nowrap;
            }
            .action-buttons .btn {
                padding: 0.25rem 0.5rem;
                font-size: 0.875rem;
                flex: 0 0 auto;
            }
            .late-time-display, .over-time-display {
                font-size: 1rem;
                font-weight: 500;
                padding: 0.25rem 0.5rem;
                border-radius: 0.375rem;
                margin-top: 0.25rem;
            }
            .late-time-warning {
                color: #dc3545;
                background-color: #f8d7da;
                border: 1px solid #f5c6cb;
            }
            .late-time-normal {
                color: #28a745;
                background-color: #d4edda;
                border: 1px solid #c3e6cb;
            }
            .over-time-warning {
                color: #28a745;
                background-color: #d4edda;
                border: 1px solid #c3e6cb;
            }
            .calculation-info {
                font-size: 0.85rem;
                margin-top: 0.25rem;
                color: #6c757d;
            }
            /* Status styles in table */
            .status-column {
                text-align: center;
            }
            
            /* Clear button for time inputs */
            .time-clear-btn {
                position: absolute;
                right: 10px;
                top: 50%;
                transform: translateY(-50%);
                background: none;
                border: none;
                color: #dc3545;
                cursor: pointer;
                display: none;
            }
            .time-clear-btn:hover {
                color: #dc3545;
            }
            .time-input-wrapper {
                position: relative;
            }
            .time-input-wrapper input {
                padding-right: 30px;
            }
            .time-input-wrapper input:not(:placeholder-shown) ~ .time-clear-btn {
                display: block;
            }
        </style>
    </head>
    <body class="sb-nav-fixed">
        <?php include 'includes/top_bar.php';?>
        <div id="layoutSidenav">
            <?php include 'includes/side_menu.php';?>
            <div id="layoutSidenav_content">
                <main>
                    <div class="container-fluid px-4">
                        <h1 class="mt-4">Payroll</h1>
                        <ol class="breadcrumb mb-4">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Payroll</li>
                        </ol>
                        
                        <div class="row">
                            <div class="col-xl-12">
                                <!-- Filter Records Form -->
                                <div class="card mb-4">
                                    <div class="card-header">
                                        <i class="fas fa-filter me-1"></i>
                                        Filter Records
                                    </div>
                                    <div class="card-body">
                                        <form method="POST" class="row g-3 align-items-end" id="filterForm">
                                            <div class="col-md-3">
                                                <label for="date_from" class="form-label">From Date</label>
                                                <input type="date" class="form-control" id="date_from" name="date_from" 
                                                       value="<?php echo $selected_date_from; ?>">
                                            </div>
                                            <div class="col-md-3">
                                                <label for="date_to" class="form-label">To Date</label>
                                                <input type="date" class="form-control" id="date_to" name="date_to" 
                                                       value="<?php echo $selected_date_to; ?>">
                                            </div>
                                            <div class="col-md-4">
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
                                            <div class="col-md-2 d-flex gap-2">
                                                <button type="submit" name="apply_filter" class="btn btn-primary flex-grow-1">
                                                    <i class="fas fa-check"></i> Apply
                                                </button>
                                                <button type="submit" name="clear_filter" class="btn btn-secondary flex-grow-1">
                                                    <i class="fas fa-times"></i> Clear
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                                
                                <!-- Attendance Records Table -->
                                <?php if (!empty($attendance_records)): ?>
                                <div class="card mb-4">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <div>
                                            <i class="fas fa-clock me-1"></i>
                                            Attendance Records (<?php echo count($attendance_records); ?> records)
                                            <?php if (!empty($selected_department)): ?>
                                                <span class="badge bg-info ms-2">Department: <?php echo htmlspecialchars($selected_department); ?></span>
                                            <?php endif; ?>
                                            <?php if (empty($selected_date_from) || empty($selected_date_to)): ?>
                                                <span class="badge bg-warning ms-2">All Dates</span>
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
                                                <i class="fas fa-file-pdf me-1"></i> Generate Payslip PDF
                                            </a>

                                            <a href="generate_payroll_pdf.php?<?php 
                                                echo http_build_query([
                                                    'date_from' => $selected_date_from,
                                                    'date_to' => $selected_date_to,
                                                    'department' => $selected_department
                                                ]); 
                                            ?>" target="_blank" class="btn btn-success btn-sm">
                                                <i class="fas fa-file-pdf me-1"></i> Generate Payroll PDF
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
                                                            <td class="check-in"><?php echo $record['check_in'] ? formatTimeTo12Hour($record['check_in']) : '--:--'; ?></td>
                                                            <td><?php echo $record['break_out'] ? formatTimeTo12Hour($record['break_out']) : '--:--'; ?></td>
                                                            <td><?php echo $record['break_in'] ? formatTimeTo12Hour($record['break_in']) : '--:--'; ?></td>
                                                            <td class="check-out"><?php echo $record['check_out'] ? formatTimeTo12Hour($record['check_out']) : '--:--'; ?></td>
                                                            <td class="<?php echo $record['late_time'] > 0 ? 'late-time' : ''; ?>">
                                                                <?php echo $record['late_time'] > 0 ? $record['late_time'] : '-'; ?>
                                                            </td>
                                                            <td class="<?php echo $record['over_time'] > 0 ? 'over-time' : ''; ?>">
                                                                <?php echo $record['over_time'] > 0 ? $record['over_time'] : '-'; ?>
                                                            </td>
                                                            <td class="status-column">
                                                                <?php
                                                                $status = $record['status'] ?? 'Present';
                                                                $status_class = 'status-present';
                                                                if ($status == 'Half Day') {
                                                                    $status_class = 'status-halfday';
                                                                } elseif ($status == 'Absent') {
                                                                    $status_class = 'status-absent';
                                                                }
                                                                ?>
                                                                <span class="status-badge <?php echo $status_class; ?>">
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
                                <div class="card mb-4">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <div>
                                            <i class="fas fa-clock me-1"></i>
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
                                                <i class="fas fa-file-pdf me-1"></i> Generate Payroll PDF
                                            </a>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="alert alert-info mb-0" id="noRecordsAlert">
                                            <i class="fas fa-info-circle me-2"></i>
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

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>
        <script>
            // PHP message to JavaScript
            <?php if ($message): ?>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: '<?php echo $message_type; ?>',
                    title: '<?php echo $message_type == 'success' ? 'Success!' : 'Error!'; ?>',
                    html: '<?php echo nl2br(addslashes($message)); ?>',
                    timer: <?php echo $message_type == 'success' ? 3000 : 5000; ?>,
                    timerProgressBar: true,
                    showConfirmButton: true
                });
            });
            <?php endif; ?>
            
            <?php if (isset($attendance_error)): ?>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Database Error',
                    text: '<?php echo addslashes($attendance_error); ?>',
                    timer: 5000,
                    timerProgressBar: true
                });
            });
            <?php endif; ?>

            // Simple sidebar toggle functionality
            document.getElementById('sidebarToggle').addEventListener('click', function() {
                document.body.classList.toggle('sb-sidenav-toggled');
            });
            
            // Date range validation
            document.getElementById('filterForm').addEventListener('submit', function(e) {
                // Check if this is a clear filter submission
                if (e.submitter && e.submitter.name === 'clear_filter') {
                    return true; // Allow clear filter to proceed without validation
                }
                
                const dateFrom = document.getElementById('date_from').value;
                const dateTo = document.getElementById('date_to').value;
                
                // Allow empty dates (will show all records)
                if (dateFrom && dateTo && dateFrom > dateTo) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'error',
                        title: 'Invalid Date Range',
                        text: '"From Date" cannot be later than "To Date".',
                        timer: 3000,
                        timerProgressBar: true
                    });
                }
            });
            
            /**
             * Convert time string to minutes since midnight
             * @param {string} timeStr - Time in HH:MM format
             * @returns {number|null} - Minutes since midnight or null if invalid
             */
            function timeToMinutes(timeStr) {
                if (!timeStr) return null;
                const parts = timeStr.split(':');
                if (parts.length < 2) return null;
                const hours = parseInt(parts[0], 10);
                const minutes = parseInt(parts[1], 10);
                if (isNaN(hours) || isNaN(minutes)) return null;
                return hours * 60 + minutes;
            }
            
            /**
             * Format minutes to readable time
             * @param {number} minutes - Minutes since midnight
             * @returns {string} - Formatted time string (HH:MM)
             */
            function minutesToTime(minutes) {
                if (minutes === null || minutes < 0) return '';
                const hours = Math.floor(minutes / 60);
                const mins = minutes % 60;
                return `${hours.toString().padStart(2, '0')}:${mins.toString().padStart(2, '0')}`;
            }
            
            /**
             * Format time to 12-hour format with AM/PM
             * @param {string} timeStr - Time in 24-hour format (HH:MM or HH:MM:SS)
             * @returns {string} - Formatted time (e.g., "8:00:56 AM")
             */
            function formatTimeTo12Hour(timeStr) {
                if (!timeStr) return '';
                
                const parts = timeStr.split(':');
                if (parts.length >= 2) {
                    let hours = parseInt(parts[0], 10);
                    const minutes = parts[1];
                    const seconds = parts.length >= 3 ? parts[2] : '00';
                    
                    const ampm = hours >= 12 ? 'PM' : 'AM';
                    hours = hours % 12;
                    hours = hours ? hours : 12; // Convert 0 to 12
                    
                    // Remove leading zero from hours if present
                    const hoursStr = hours.toString();
                    
                    return `${hoursStr}:${minutes}:${seconds} ${ampm}`;
                }
                
                return timeStr;
            }
            
            /**
             * Calculate late minutes based on check-in and break-in times
             * @param {string} checkIn - Check-in time in HH:MM format
             * @param {string} breakIn - Break-in time in HH:MM format
             * @returns {number} - Total late minutes
             */
            function calculateLateMinutes(checkIn, breakIn) {
                // Standard times in minutes since midnight
                // 7:31 AM = 7*60 + 31 = 420 + 31 = 451 minutes
                // 1:15 PM = 13*60 + 15 = 780 + 15 = 795 minutes
                const STANDARD_CHECK_IN = 7 * 60 + 31; // 07:31
                const STANDARD_BREAK_IN = 13 * 60 + 15; // 13:15 (1:15 PM)
                
                const checkInMinutes = timeToMinutes(checkIn);
                const breakInMinutes = timeToMinutes(breakIn);
                
                let totalLateMinutes = 0;
                
                // Calculate late minutes from Check In
                if (checkInMinutes !== null && checkInMinutes > STANDARD_CHECK_IN) {
                    totalLateMinutes += (checkInMinutes - STANDARD_CHECK_IN);
                }
                
                // Calculate late minutes from Break In
                if (breakInMinutes !== null && breakInMinutes > STANDARD_BREAK_IN) {
                    totalLateMinutes += (breakInMinutes - STANDARD_BREAK_IN);
                }
                
                return Math.max(0, totalLateMinutes); // Ensure non-negative
            }
            
            /**
             * Calculate overtime minutes based on check-in (before 7:00 AM) and check-out (after 6:00 PM)
             * @param {string} checkIn - Check-in time in HH:MM format
             * @param {string} checkOut - Check-out time in HH:MM format
             * @returns {number} - Total overtime minutes
             */
            function calculateOvertime(checkIn, checkOut) {
                let totalOvertime = 0;
                
                // Standard times in minutes since midnight
                const STANDARD_START = 7 * 60; // 07:00
                const STANDARD_END = 18 * 60; // 18:00 (6:00 PM)
                
                // Calculate overtime from early check-in (before 7:00 AM)
                if (checkIn) {
                    const checkInMinutes = timeToMinutes(checkIn);
                    if (checkInMinutes !== null && checkInMinutes < STANDARD_START) {
                        totalOvertime += (STANDARD_START - checkInMinutes);
                    }
                }
                
                // Calculate overtime from late check-out (after 6:00 PM)
                // Only count if check-out is 6:00 PM (18:00) or later
                if (checkOut) {
                    const checkOutMinutes = timeToMinutes(checkOut);
                    if (checkOutMinutes !== null && checkOutMinutes >= STANDARD_END) {
                        totalOvertime += (checkOutMinutes - STANDARD_END);
                    }
                }
                
                return totalOvertime;
            }
            
            /**
             * Get the breakdown of lateness
             * @param {string} checkIn - Check-in time in HH:MM format
             * @param {string} breakIn - Break-in time in HH:MM format
             * @returns {object} - Breakdown of late minutes
             */
            function getLateBreakdown(checkIn, breakIn) {
                // Standard times in minutes since midnight
                const STANDARD_CHECK_IN = 7 * 60 + 31; // 07:31
                const STANDARD_BREAK_IN = 13 * 60 + 15; // 13:15 (1:15 PM)
                
                const checkInMinutes = timeToMinutes(checkIn);
                const breakInMinutes = timeToMinutes(breakIn);
                
                let checkInLate = 0;
                let breakInLate = 0;
                let checkInReason = '';
                let breakInReason = '';
                
                // Calculate Check In late
                if (checkInMinutes !== null && checkInMinutes > STANDARD_CHECK_IN) {
                    checkInLate = checkInMinutes - STANDARD_CHECK_IN;
                    checkInReason = `Check In at ${formatTimeTo12Hour(checkIn)} (${checkInLate} min late)`;
                } else if (checkInMinutes !== null) {
                    checkInReason = `Check In at ${formatTimeTo12Hour(checkIn)} (on time)`;
                }
                
                // Calculate Break In late
                if (breakInMinutes !== null && breakInMinutes > STANDARD_BREAK_IN) {
                    breakInLate = breakInMinutes - STANDARD_BREAK_IN;
                    breakInReason = `Break In at ${formatTimeTo12Hour(breakIn)} (${breakInLate} min late)`;
                } else if (breakInMinutes !== null) {
                    breakInReason = `Break In at ${formatTimeTo12Hour(breakIn)} (on time)`;
                }
                
                return {
                    total: checkInLate + breakInLate,
                    checkInLate: checkInLate,
                    breakInLate: breakInLate,
                    checkInReason: checkInReason,
                    breakInReason: breakInReason
                };
            }
            
            /**
             * Get the breakdown of overtime
             * @param {string} checkIn - Check-in time in HH:MM format
             * @param {string} checkOut - Check-out time in HH:MM format
             * @returns {object} - Breakdown of overtime minutes
             */
            function getOvertimeBreakdown(checkIn, checkOut) {
                const STANDARD_START = 7 * 60; // 07:00
                const STANDARD_END = 18 * 60; // 18:00 (6:00 PM)
                
                let earlyOvertime = 0;
                let lateOvertime = 0;
                let earlyReason = '';
                let lateReason = '';
                
                // Calculate early overtime
                if (checkIn) {
                    const checkInMinutes = timeToMinutes(checkIn);
                    if (checkInMinutes !== null) {
                        if (checkInMinutes < STANDARD_START) {
                            earlyOvertime = STANDARD_START - checkInMinutes;
                            earlyReason = `Early Check In: ${formatTimeTo12Hour(checkIn)} (${earlyOvertime} min)`;
                        } else {
                            earlyReason = `Check In: ${formatTimeTo12Hour(checkIn)} (no early overtime)`;
                        }
                    }
                }
                
                // Calculate late overtime (only if check-out is 6:00 PM or later)
                if (checkOut) {
                    const checkOutMinutes = timeToMinutes(checkOut);
                    if (checkOutMinutes !== null) {
                        if (checkOutMinutes > STANDARD_END) {
                            lateOvertime = checkOutMinutes - STANDARD_END;
                            lateReason = `Late Check Out: ${formatTimeTo12Hour(checkOut)} (${lateOvertime} min)`;
                        } else if (checkOutMinutes === STANDARD_END) {
                            lateOvertime = 0;
                            lateReason = `Check Out: ${formatTimeTo12Hour(checkOut)} (exactly 6:00 PM - no overtime)`;
                        } else {
                            lateReason = `Check Out: ${formatTimeTo12Hour(checkOut)} (before 6:00 PM - no overtime)`;
                        }
                    }
                }
                
                return {
                    total: earlyOvertime + lateOvertime,
                    earlyOvertime: earlyOvertime,
                    lateOvertime: lateOvertime,
                    earlyReason: earlyReason,
                    lateReason: lateReason
                };
            }
            
            document.addEventListener('DOMContentLoaded', function() {
                // Initialize DataTable for attendance records only
                if (document.getElementById('attendanceRecordsTable')) {
                    new simpleDatatables.DataTable("#attendanceRecordsTable", {
                        searchable: true,
                        fixedHeight: false,
                        perPage: 10,
                        labels: {
                            placeholder: "Search...",
                            perPage: "{select} records per page",
                            noRows: "No records found",
                            info: "Showing {start} to {end} of {rows} records"
                        }
                    });
                }
            });
        </script>
    </body>
</html>