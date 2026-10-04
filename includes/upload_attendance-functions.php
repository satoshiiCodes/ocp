<?php
/**
 * includes/upload_attendance-functions.php
 *
 * The helpers upload_attendance.php and its actions file share: the attendance time
 * parsing and status calculations, the CSV/Excel parsers, and the routine that
 * writes parsed attendance into the database.
 *
 * They live in their own file because both entry points need them. The POST
 * dispatcher calls formatEmployeeName(), parseCSVFile(), parseExcelFile() and
 * saveAttendanceToDatabase(); the rendering below the dispatcher calls
 * formatTimeTo12Hour(). Several call each other, so they must be loaded before
 * whichever runs first - which is why both actions/upload_attendance-actions.php
 * and upload_attendance.php require this file.
 *
 * The bodies below are lifted verbatim from upload_attendance.php; nothing changed.
 * They intentionally duplicate the same-named helpers in payroll.php: the two pages
 * are never loaded in the same request, and merging them would be a behaviour
 * change rather than a restructure.
 */

// PhpSpreadsheet, for parseExcelFile() below. The `use` statement is what was lost when
// this helper moved out of upload_attendance.php: the bare name then resolves to the global
// namespace, and PHP aborted with "Class IOFactory not found" on any spreadsheet upload.
use PhpOffice\PhpSpreadsheet\IOFactory;

if (defined('OCP_UPLOAD_ATTENDANCE_FUNCTIONS_LOADED')) {
    return;
}
define('OCP_UPLOAD_ATTENDANCE_FUNCTIONS_LOADED', true);

// The autoloader is pulled in here as well, so the helper works whichever entry point
// reaches it first: the page requires vendor/autoload.php, but a direct POST to the actions
// file is not obliged to, and this file must not depend on that.
if (!class_exists(IOFactory::class)) {
    $__ocp_autoload = __DIR__ . '/../vendor/autoload.php';
    if (is_file($__ocp_autoload)) {
        require_once $__ocp_autoload;
    }
    unset($__ocp_autoload);
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
