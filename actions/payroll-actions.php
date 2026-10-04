<?php
/**
 * actions/payroll-actions.php
 *
 * Every action for payroll.php lives in this one file: applying and clearing the
 * view filters, deleting and editing an attendance record, adding one by hand, and
 * importing an attendance file.
 *
 * The page pulls this file in at the top, so it runs in the page's scope. Every
 * branch here re-renders rather than redirecting, so the messages and the chosen
 * filter dates are left in scope for the markup below, exactly as when this code
 * sat inline.
 *
 * The helper functions this calls live in includes/payroll-functions.php, which is
 * required below so they are defined whichever entry point runs first.
 *
 * The block below is lifted verbatim from payroll.php: the queries, the parsing and
 * the messages are unchanged.
 */

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    return;
}
if (defined('OCP_PAYROLL_ACTIONS_RAN')) {
    return;
}
define('OCP_PAYROLL_ACTIONS_RAN', true);

require_once __DIR__ . '/../includes/payroll-functions.php';

// The message variables and the chosen filters. They are filled in below and read
// by the markup.
$message = '';
$message_type = '';
$uploaded_data = [];
$selected_date_from = '';
$selected_date_to = '';
$selected_department = '';
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
