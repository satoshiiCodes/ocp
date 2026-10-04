<?php
/**
 * includes/payroll-functions.php
 *
 * The helpers payroll.php and its actions file share: the overtime and attendance
 * calculations, the CSV/Excel parsers, and the routine that writes parsed
 * attendance into the database.
 *
 * They live in their own file because both entry points need them. The POST
 * dispatcher calls parseCSVFile(), parseExcelFile(), parseAttendanceData(),
 * convertExcelTime(), formatEmployeeName(), calculateOvertime(),
 * calculateAttendanceStatus() and saveAttendanceToDatabase(); the rendering below
 * the dispatcher calls formatTimeTo12Hour(). Several of them call each other, so
 * they have to be loaded before whichever runs first - which is why both
 * actions/payroll-actions.php and payroll.php require this file.
 *
 * The bodies below are lifted verbatim from payroll.php; nothing was changed.
 */

if (defined('OCP_PAYROLL_FUNCTIONS_LOADED')) {
    return;
}
define('OCP_PAYROLL_FUNCTIONS_LOADED', true);

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
