<?php
session_start();
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Database connection
require_once 'includes/db_config.php';

// Set timezone to Manila
date_default_timezone_set('Asia/Manila');

// Load DOMPDF library
require_once 'vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Get filter parameters from URL
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$department = isset($_GET['department']) ? $_GET['department'] : '';

// Get user details for footer
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

/**
 * Calculate late deduction based on minutes late and daily rate
 * Formula: (Avg Daily Rate / 8 hours) / 60 * Late Minutes
 */
function calculateLateDeduction($daily_rate, $late_minutes) {
    if ($daily_rate <= 0 || $late_minutes <= 0) {
        return 0;
    }
    // Assuming 8-hour work day
    // Calculate per minute rate: (Daily Rate / 8 hours) / 60 minutes
    $per_minute_rate = ($daily_rate / 8) / 60;
    return $per_minute_rate * $late_minutes;
}

/**
 * Calculate overtime pay based on minutes overtime and daily rate
 * Formula: (Avg Daily Rate / 8 hours) / 60 * Overtime Minutes
 */
function calculateOvertimePay($daily_rate, $overtime_minutes) {
    if ($daily_rate <= 0 || $overtime_minutes <= 0) {
        return 0;
    }
    // Assuming 8-hour work day
    // Calculate per minute rate: (Daily Rate / 8 hours) / 60 minutes
    $per_minute_rate = ($daily_rate / 8) / 60;
    return $per_minute_rate * $overtime_minutes;
}

/**
 * Get wage history for an employee to display below the average
 */
function getEmployeeWageHistory($pdo, $employee_id, $date_from = null, $date_to = null) {
    try {
        $sql = "
            SELECT effectivity_date, new_wage
            FROM wage_history
            WHERE employee_id = :employee_id
        ";
        
        $params = [':employee_id' => $employee_id];
        
        // If date range is provided, filter to relevant history
        if (!empty($date_from) && !empty($date_to)) {
            $sql .= " AND effectivity_date <= :date_to";
            $params[':date_to'] = $date_to;
        }
        
        $sql .= " ORDER BY effectivity_date DESC LIMIT 3"; // Get last 3 changes
        
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch(PDOException $e) {
        error_log("Error fetching wage history: " . $e->getMessage());
        return [];
    }
}

/**
 * Get cash advance total for an employee within a date range
 */
function getEmployeeCashAdvance($pdo, $employee_id, $date_from, $date_to) {
    try {
        if (empty($date_from) || empty($date_to)) {
            return 0;
        }
        
        $sql = "
            SELECT SUM(amount) as total_cash_advance
            FROM employee_deductions
            WHERE employee_id = :employee_id
            AND deduction_type = 'cash_advance'
            AND from_date <= :date_to
            AND to_date >= :date_from
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':employee_id', $employee_id);
        $stmt->bindParam(':date_from', $date_from);
        $stmt->bindParam(':date_to', $date_to);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['total_cash_advance'] ? floatval($result['total_cash_advance']) : 0;
        
    } catch(PDOException $e) {
        error_log("Error fetching cash advance for employee $employee_id: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get government deductions (SSS, Pag-IBIG, PhilHealth) for an employee based on effectivity date
 */
function getEmployeeGovernmentDeductions($pdo, $employee_id, $effectivity_date) {
    try {
        $deductions = [
            'sss' => 0,
            'pag_ibig' => 0,
            'philhealth' => 0
        ];
        
        $sql = "
            SELECT deduction_type, amount
            FROM employee_deductions
            WHERE employee_id = :employee_id
            AND deduction_type IN ('sss', 'pag_ibig', 'philhealth')
            AND effectivity_date <= :effectivity_date
            ORDER BY effectivity_date DESC
            LIMIT 3
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':employee_id', $employee_id);
        $stmt->bindParam(':effectivity_date', $effectivity_date);
        $stmt->execute();
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($results as $row) {
            switch ($row['deduction_type']) {
                case 'sss':
                    $deductions['sss'] = floatval($row['amount']);
                    break;
                case 'pag_ibig':
                    $deductions['pag_ibig'] = floatval($row['amount']);
                    break;
                case 'philhealth':
                    $deductions['philhealth'] = floatval($row['amount']);
                    break;
            }
        }
        
        return $deductions;
        
    } catch(PDOException $e) {
        error_log("Error fetching government deductions for employee $employee_id: " . $e->getMessage());
        return [
            'sss' => 0,
            'pag_ibig' => 0,
            'philhealth' => 0
        ];
    }
}

/**
 * Calculate average daily rate for an employee based on present days
 * Takes into account wage changes on specific effectivity dates
 * Average = Total Wages Earned / Total Present Days
 */
function calculateAverageDailyRate($pdo, $employee_id, $date_from = null, $date_to = null, $attendance_map = null) {
    try {
        // If attendance map is provided, use it to calculate based on actual present days
        if ($attendance_map !== null && isset($attendance_map[$employee_id]) && !empty($attendance_map[$employee_id])) {
            $total_wages = 0;
            $total_present_days = 0;
            
            foreach ($attendance_map[$employee_id] as $date => $record) {
                // Get the wage for this specific date
                $daily_rate = getEmployeeWageOnDate($pdo, $employee_id, $date);
                $status = $record['status'] ?? 'Present';
                
                // Calculate wage based on status
                if ($status == 'Present') {
                    $total_wages += $daily_rate;
                    $total_present_days += 1;
                } elseif ($status == 'Half Day') {
                    $total_wages += ($daily_rate / 2);
                    $total_present_days += 0.5;
                }
                // Absent contributes 0 to both wages and present days
            }
            
            // Calculate average based on present days
            if ($total_present_days > 0) {
                return $total_wages / $total_present_days;
            }
        }
        
        // If no attendance map provided or no attendance records, fall back to original calculation
        // If no date range provided, get the overall average based on all wage history
        if (empty($date_from) || empty($date_to)) {
            // Get all wage history for this employee
            $sql = "
                SELECT effectivity_date, new_wage
                FROM wage_history
                WHERE employee_id = :employee_id
                ORDER BY effectivity_date ASC
            ";
            
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':employee_id', $employee_id);
            $stmt->execute();
            
            $wage_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($wage_history)) {
                return 0.00;
            }
            
            // Get the earliest and latest dates from wage history
            $first_wage = $wage_history[0];
            $last_wage = $wage_history[count($wage_history) - 1];
            
            // Set date range from first effectivity date to today (or last effectivity date + 30 days)
            $date_from = $first_wage['effectivity_date'];
            $date_to = date('Y-m-d', strtotime($last_wage['effectivity_date'] . ' +30 days'));
            
            // If there's only one wage entry, just return that wage
            if (count($wage_history) == 1) {
                return floatval($first_wage['new_wage']);
            }
        }
        
        // Get all wage history entries that are relevant to the date range
        $sql = "
            SELECT effectivity_date, new_wage
            FROM wage_history
            WHERE employee_id = :employee_id
            AND effectivity_date <= :date_to
            ORDER BY effectivity_date ASC
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':employee_id', $employee_id);
        $stmt->bindParam(':date_to', $date_to);
        $stmt->execute();
        
        $wage_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($wage_history)) {
            return 0.00;
        }
        
        // Add the date range start as a reference point
        $period_start = strtotime($date_from);
        $period_end = strtotime($date_to);
        
        // Build wage periods
        $wage_periods = [];
        $prev_wage = null;
        $prev_date = null;
        
        foreach ($wage_history as $index => $wage) {
            $effectivity_date = strtotime($wage['effectivity_date']);
            $wage_amount = floatval($wage['new_wage']);
            
            // If this effectivity date is before our period start, use it as the starting wage
            if ($effectivity_date <= $period_start) {
                $prev_wage = $wage_amount;
                $prev_date = $period_start;
                continue;
            }
            
            // If we have a previous wage, calculate the period from prev_date to day before this effectivity
            if ($prev_wage !== null && $prev_date !== null) {
                $period_end_date = min($effectivity_date - 86400, $period_end); // One day before next effectivity
                
                if ($period_end_date >= $prev_date) {
                    $days = floor(($period_end_date - $prev_date) / 86400) + 1;
                    if ($days > 0) {
                        $wage_periods[] = [
                            'wage' => $prev_wage,
                            'days' => $days
                        ];
                    }
                }
            }
            
            $prev_wage = $wage_amount;
            $prev_date = $effectivity_date;
        }
        
        // Handle the last period from the last effectivity date to period end
        if ($prev_wage !== null && $prev_date !== null) {
            $last_period_end = min($period_end, strtotime($date_to));
            if ($last_period_end >= $prev_date) {
                $days = floor(($last_period_end - $prev_date) / 86400) + 1;
                if ($days > 0) {
                    $wage_periods[] = [
                        'wage' => $prev_wage,
                        'days' => $days
                    ];
                }
            }
        }
        
        // Calculate weighted average
        $total_days = 0;
        $total_wage_days = 0;
        
        foreach ($wage_periods as $period) {
            $total_days += $period['days'];
            $total_wage_days += ($period['wage'] * $period['days']);
        }
        
        if ($total_days > 0) {
            return $total_wage_days / $total_days;
        }
        
        // If no periods calculated, use the most recent wage before or on date_to
        $stmt = $pdo->prepare("
            SELECT new_wage 
            FROM wage_history 
            WHERE employee_id = :employee_id 
            AND effectivity_date <= :date_to 
            ORDER BY effectivity_date DESC 
            LIMIT 1
        ");
        $stmt->bindParam(':employee_id', $employee_id);
        $stmt->bindParam(':date_to', $date_to);
        $stmt->execute();
        $latest_wage = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $latest_wage ? floatval($latest_wage['new_wage']) : 0.00;
        
    } catch(PDOException $e) {
        error_log("Error calculating average wage for employee $employee_id: " . $e->getMessage());
        return 0.00;
    }
}

/**
 * Get the date range for "All Dates" based on attendance records
 */
function getOverallDateRange($pdo) {
    try {
        $sql = "SELECT MIN(attendance_date) as min_date, MAX(attendance_date) as max_date FROM attendance";
        $stmt = $pdo->query($sql);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && $result['min_date'] && $result['max_date']) {
            return [
                'min_date' => $result['min_date'],
                'max_date' => $result['max_date']
            ];
        }
        
        // If no attendance records, use current month
        return [
            'min_date' => date('Y-m-01'),
            'max_date' => date('Y-m-t')
        ];
        
    } catch(PDOException $e) {
        error_log("Error getting date range: " . $e->getMessage());
        return [
            'min_date' => date('Y-m-01'),
            'max_date' => date('Y-m-t')
        ];
    }
}

// Function to get wage for a specific employee on a specific date
function getEmployeeWageOnDate($pdo, $employee_id, $attendance_date) {
    try {
        $sql = "
            SELECT new_wage as daily_rate
            FROM wage_history
            WHERE employee_id = :employee_id
            AND effectivity_date <= :attendance_date
            ORDER BY effectivity_date DESC
            LIMIT 1
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':employee_id', $employee_id);
        $stmt->bindParam(':attendance_date', $attendance_date);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && isset($result['daily_rate'])) {
            return floatval($result['daily_rate']);
        }
        
        // If no wage history found, return 0
        return 0.00;
        
    } catch(PDOException $e) {
        error_log("Error fetching wage for employee $employee_id on $attendance_date: " . $e->getMessage());
        return 0.00;
    }
}

// Function to calculate work days between two dates (including Sundays)
function calculateWorkDays($start_date, $end_date) {
    $work_days = 0;
    $current = strtotime($start_date);
    $end = strtotime($end_date);
    
    while ($current <= $end) {
        // Include ALL days (including Sundays)
        $work_days++;
        $current = strtotime('+1 day', $current);
    }
    
    return $work_days;
}

// Fetch attendance records with filters
$attendance_records = [];
$summary_stats = [
    'total_records' => 0,
    'total_late_minutes' => 0,
    'total_overtime_minutes' => 0,
    'present_count' => 0,
    'halfday_count' => 0,
    'absent_count' => 0,
    'employees_count' => 0,
    'total_hours_worked' => 0,
    'total_daily_rate' => 0,
    'total_wages' => 0,
    'work_days' => 0,
    'actual_min_date' => '',
    'actual_max_date' => '',
    'total_present_days' => 0, // Total present days (Present = 1, Half Day = 0.5)
    'total_absent_days' => 0,    // Total absent days (Absent = 1, Half Day = 0.5, No record = 1)
    'total_late_deductions' => 0, // Total late deductions
    'total_overtime_pay' => 0,     // Total overtime pay
    'total_cash_advance' => 0,      // Total cash advance
    'total_sss' => 0,               // Total SSS deductions
    'total_pag_ibig' => 0,          // Total Pag-IBIG deductions
    'total_philhealth' => 0,         // Total PhilHealth deductions
    'total_deductions' => 0,           // Total deductions (late + cash advance + sss + pag-ibig + philhealth)
    'total_gross' => 0,                 // Total gross (sum of all employee gross)
    'total_net' => 0                    // Total net (sum of all employee net)
];

try {
    $sql = "
        SELECT a.*, 
               e.employee_id as emp_code,
               e.id as employee_db_id,
               e.position as employee_position
        FROM attendance a
        JOIN employee e ON a.employee_id = e.id
        WHERE 1=1
    ";
    $params = [];
    
    // Apply date range filter
    if (!empty($date_from) && !empty($date_to)) {
        $sql .= " AND a.attendance_date BETWEEN :date_from AND :date_to";
        $params[':date_from'] = $date_from;
        $params[':date_to'] = $date_to;
        $summary_stats['actual_min_date'] = $date_from;
        $summary_stats['actual_max_date'] = $date_to;
    }
    
    // Apply department filter
    if (!empty($department)) {
        $sql .= " AND a.department = :department";
        $params[':department'] = $department;
    }
    
    $sql .= " ORDER BY a.attendance_date DESC, a.employee_name ASC";
    
    $attendanceStmt = $pdo->prepare($sql);
    
    foreach ($params as $key => $value) {
        $attendanceStmt->bindValue($key, $value);
    }
    
    $attendanceStmt->execute();
    $attendance_records = $attendanceStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate date range for work days
    if (!empty($attendance_records)) {
        // Get min and max dates from records
        $dates = array_column($attendance_records, 'attendance_date');
        $min_date = min($dates);
        $max_date = max($dates);
        
        // If date filters are provided, use them instead
        if (!empty($date_from) && !empty($date_to)) {
            $min_date = $date_from;
            $max_date = $date_to;
            $summary_stats['actual_min_date'] = $date_from;
            $summary_stats['actual_max_date'] = $date_to;
        } else {
            // For "All Dates", use the actual min and max from records
            $summary_stats['actual_min_date'] = $min_date;
            $summary_stats['actual_max_date'] = $max_date;
        }
        
        $summary_stats['work_days'] = calculateWorkDays($min_date, $max_date);
    } else {
        // If no records, set default date range
        $date_range = getOverallDateRange($pdo);
        $summary_stats['actual_min_date'] = $date_range['min_date'];
        $summary_stats['actual_max_date'] = $date_range['max_date'];
        $summary_stats['work_days'] = calculateWorkDays($date_range['min_date'], $date_range['max_date']);
    }
    
    // Calculate summary statistics and fetch wages
    $unique_employees = [];
    $total_wages = 0;
    $employee_average_rates = []; // Cache for average rates
    $employee_wage_history = []; // Cache for wage history
    $employee_cash_advance = []; // Cache for cash advance totals
    $employee_government_deductions = []; // Cache for government deductions
    $total_present_days = 0; // Initialize total present days counter
    $total_absent_days = 0; // Initialize total absent days counter
    $total_cash_advance = 0; // Initialize total cash advance counter
    $total_sss = 0; // Initialize total SSS counter
    $total_pag_ibig = 0; // Initialize total Pag-IBIG counter
    $total_philhealth = 0; // Initialize total PhilHealth counter
    $total_deductions = 0; // Initialize total deductions counter
    $total_gross = 0; // Initialize total gross counter
    $total_net = 0; // Initialize total net counter
    
    // Get all employees
    $employee_sql = "SELECT id FROM employee";
    $employeeStmt = $pdo->prepare($employee_sql);
    $employeeStmt->execute();
    $all_employees = $employeeStmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Create a map of attendance records by employee and date
    $attendance_map = [];
    foreach ($attendance_records as $record) {
        $emp_id = $record['employee_db_id'];
        $date = $record['attendance_date'];
        if (!isset($attendance_map[$emp_id])) {
            $attendance_map[$emp_id] = [];
        }
        $attendance_map[$emp_id][$date] = $record;
    }
    
    // Calculate present and absent days for each employee
    $employee_present_days = []; // Array to store present days per employee
    $employee_absent_days = []; // Array to store absent days per employee
    $employee_total_late = []; // Array to store total late minutes per employee
    $employee_total_overtime = []; // Array to store total overtime minutes per employee
    $employee_late_deductions = []; // Array to store total late deductions per employee
    $employee_overtime_pay = []; // Array to store total overtime pay per employee
    $employee_total_deductions = []; // Array to store total deductions per employee
    $employee_gross = []; // Array to store gross per employee
    $employee_net = []; // Array to store net per employee
    
    // Generate all dates in the range
    $all_dates = [];
    $current_date = strtotime($summary_stats['actual_min_date']);
    $end_date = strtotime($summary_stats['actual_max_date']);
    while ($current_date <= $end_date) {
        $all_dates[] = date('Y-m-d', $current_date);
        $current_date = strtotime('+1 day', $current_date);
    }
    
    foreach ($all_employees as $emp_id) {
        $employee_present_days[$emp_id] = 0;
        $employee_absent_days[$emp_id] = 0;
        $employee_total_late[$emp_id] = 0;
        $employee_total_overtime[$emp_id] = 0;
        $employee_late_deductions[$emp_id] = 0;
        $employee_overtime_pay[$emp_id] = 0;
        
        // Calculate cash advance for this employee
        if (!empty($date_from) && !empty($date_to)) {
            $employee_cash_advance[$emp_id] = getEmployeeCashAdvance($pdo, $emp_id, $date_from, $date_to);
            $total_cash_advance += $employee_cash_advance[$emp_id];
            
            // Get government deductions based on the effectivity date (using date_to as reference)
            $employee_government_deductions[$emp_id] = getEmployeeGovernmentDeductions($pdo, $emp_id, $date_to);
            $total_sss += $employee_government_deductions[$emp_id]['sss'];
            $total_pag_ibig += $employee_government_deductions[$emp_id]['pag_ibig'];
            $total_philhealth += $employee_government_deductions[$emp_id]['philhealth'];
        } else {
            // If no date range, use the overall date range from records
            $employee_cash_advance[$emp_id] = getEmployeeCashAdvance($pdo, $emp_id, $summary_stats['actual_min_date'], $summary_stats['actual_max_date']);
            $total_cash_advance += $employee_cash_advance[$emp_id];
            
            // Get government deductions based on the effectivity date (using max_date as reference)
            $employee_government_deductions[$emp_id] = getEmployeeGovernmentDeductions($pdo, $emp_id, $summary_stats['actual_max_date']);
            $total_sss += $employee_government_deductions[$emp_id]['sss'];
            $total_pag_ibig += $employee_government_deductions[$emp_id]['pag_ibig'];
            $total_philhealth += $employee_government_deductions[$emp_id]['philhealth'];
        }
        
        foreach ($all_dates as $date) {
            if (isset($attendance_map[$emp_id][$date])) {
                $record = $attendance_map[$emp_id][$date];
                $status = $record['status'] ?? 'Present';
                
                // Add late minutes and deductions for this employee
                if (isset($record['late_time']) && $record['late_time'] > 0) {
                    $employee_total_late[$emp_id] += intval($record['late_time']);
                    
                    // Get daily rate for this date to calculate deduction
                    $daily_rate = getEmployeeWageOnDate($pdo, $emp_id, $date);
                    $late_deduction = calculateLateDeduction($daily_rate, intval($record['late_time']));
                    $employee_late_deductions[$emp_id] += $late_deduction;
                }
                
                // Add overtime minutes and pay for this employee
                if (isset($record['over_time']) && $record['over_time'] > 0) {
                    $employee_total_overtime[$emp_id] += intval($record['over_time']);
                    
                    // Get daily rate for this date to calculate overtime pay
                    $daily_rate = getEmployeeWageOnDate($pdo, $emp_id, $date);
                    $overtime_pay = calculateOvertimePay($daily_rate, intval($record['over_time']));
                    $employee_overtime_pay[$emp_id] += $overtime_pay;
                }
                
                if ($status == 'Present') {
                    $employee_present_days[$emp_id] += 1;
                    $employee_absent_days[$emp_id] += 0;
                } elseif ($status == 'Half Day') {
                    $employee_present_days[$emp_id] += 0.5;
                    $employee_absent_days[$emp_id] += 0.5; // Half day counts as 0.5 absent
                } elseif ($status == 'Absent') {
                    $employee_present_days[$emp_id] += 0;
                    $employee_absent_days[$emp_id] += 1; // Absent counts as 1
                }
            } else {
                // No record for this date - employee is absent
                $employee_absent_days[$emp_id] += 1; // No record = 1 absent
            }
        }
        
        // Calculate average daily rate for this employee
        $avg_daily_rate = calculateAverageDailyRate($pdo, $emp_id, $summary_stats['actual_min_date'], $summary_stats['actual_max_date'], $attendance_map);
        $employee_average_rates[$emp_id] = $avg_daily_rate;
        
        // Calculate gross for this employee: (Avg Daily Rate * Present Days) + Overtime Pay
        $gross = ($avg_daily_rate * $employee_present_days[$emp_id]) + $employee_overtime_pay[$emp_id];
        $employee_gross[$emp_id] = $gross;
        $total_gross += $gross;
        
        // Calculate total deductions for this employee (late deductions + cash advance + sss + pag-ibig + philhealth)
        $employee_total_deductions[$emp_id] = $employee_late_deductions[$emp_id] + 
                                             $employee_cash_advance[$emp_id] + 
                                             $employee_government_deductions[$emp_id]['sss'] + 
                                             $employee_government_deductions[$emp_id]['pag_ibig'] + 
                                             $employee_government_deductions[$emp_id]['philhealth'];
        
        // Calculate net for this employee (Gross - Total Deductions)
        $net = $gross - $employee_total_deductions[$emp_id];
        $employee_net[$emp_id] = $net;
        $total_net += $net;
        
        $total_present_days += $employee_present_days[$emp_id];
        $total_absent_days += $employee_absent_days[$emp_id];
        $total_deductions += $employee_total_deductions[$emp_id];
    }
    
    // Update summary totals
    $summary_stats['total_sss'] = $total_sss;
    $summary_stats['total_pag_ibig'] = $total_pag_ibig;
    $summary_stats['total_philhealth'] = $total_philhealth;
    $summary_stats['total_deductions'] = $total_deductions;
    $summary_stats['total_gross'] = $total_gross;
    $summary_stats['total_net'] = $total_net;
    
    // Calculate average rates for each employee based on present days
    foreach ($attendance_records as &$record) {
        $unique_employees[$record['employee_id']] = true;
        
        // Calculate total late minutes
        $summary_stats['total_late_minutes'] += intval($record['late_time']);
        
        // Calculate total overtime minutes
        $summary_stats['total_overtime_minutes'] += intval($record['over_time']);
        
        // Count status
        $status = $record['status'] ?? 'Present';
        if ($status == 'Present') {
            $summary_stats['present_count']++;
        } elseif ($status == 'Half Day') {
            $summary_stats['halfday_count']++;
        } elseif ($status == 'Absent') {
            $summary_stats['absent_count']++;
        }
        
        // Calculate total hours worked
        $hours_worked = 0;
        if (!empty($record['check_in']) && !empty($record['check_out'])) {
            $check_in = strtotime($record['check_in']);
            $check_out = strtotime($record['check_out']);
            if ($check_out > $check_in) {
                $hours_worked = ($check_out - $check_in) / 3600; // Convert seconds to hours
                $summary_stats['total_hours_worked'] += $hours_worked;
            }
        }
        
        // Get daily rate for this employee on this attendance date
        $daily_rate = getEmployeeWageOnDate($pdo, $record['employee_db_id'], $record['attendance_date']);
        $record['daily_rate'] = $daily_rate;
        
        // Calculate wage for this day (based on status and hours worked)
        $daily_wage = 0;
        if ($status == 'Present') {
            $daily_wage = $daily_rate; // Full day
        } elseif ($status == 'Half Day') {
            $daily_wage = $daily_rate / 2; // Half day
        } elseif ($status == 'Absent') {
            $daily_wage = 0; // No pay
        }
        
        // Calculate late deduction for this record
        $late_deduction = calculateLateDeduction($daily_rate, intval($record['late_time']));
        $record['late_deduction'] = $late_deduction;
        $summary_stats['total_late_deductions'] += $late_deduction;
        
        // Calculate overtime pay for this record
        $overtime_pay = calculateOvertimePay($daily_rate, intval($record['over_time']));
        $record['overtime_pay'] = $overtime_pay;
        $summary_stats['total_overtime_pay'] += $overtime_pay;
        
        $record['daily_wage'] = $daily_wage;
        $total_wages += $daily_wage;
        
        $summary_stats['total_daily_rate'] += $daily_rate;
        
        // Calculate average daily rate for this employee based on present days
        $employee_key = $record['employee_db_id'];
        if (!isset($employee_average_rates[$employee_key])) {
            // Use attendance map to calculate average based on present days
            $employee_average_rates[$employee_key] = calculateAverageDailyRate($pdo, $employee_key, $summary_stats['actual_min_date'], $summary_stats['actual_max_date'], $attendance_map);
            
            // Get wage history for this employee
            $employee_wage_history[$employee_key] = getEmployeeWageHistory($pdo, $employee_key, $summary_stats['actual_min_date'], $summary_stats['actual_max_date']);
        }
    }
    
    $summary_stats['employees_count'] = count($unique_employees);
    $summary_stats['total_wages'] = $total_wages;
    $summary_stats['total_records'] = count($attendance_records);
    $summary_stats['total_present_days'] = $total_present_days;
    $summary_stats['total_absent_days'] = $total_absent_days;
    $summary_stats['total_cash_advance'] = $total_cash_advance;
    
} catch(PDOException $e) {
    // Handle error gracefully
    $error_message = "Error fetching attendance records: " . $e->getMessage();
}

// Get the absolute file path for the logo
$logo_file_path = __DIR__ . '/img/logo/OCP.png';
// Convert to file:// URL for Dompdf
$logo_url = 'file://' . str_replace('\\', '/', $logo_file_path);

// Check if logo exists, if not, we'll just show text
$logo_html = '';
if (file_exists($logo_file_path)) {
    $logo_html = '<img src="' . $logo_url . '" class="company-logo" alt="OCP Logo" />';
} else {
    // Log that logo is missing (optional)
    error_log("Logo file not found at: " . $logo_file_path);
}

/**
 * Generate the HTML content for PDF
 */
function generatePDFHTML($records, $summary, $date_from, $date_to, $department, $generated_by, $pdo = null, $wage_history = [], $employee_average_rates = [], $logo_html = '') {
    // Format date range
    $date_range_text = 'All Dates';
    if (!empty($date_from) && !empty($date_to)) {
        $date_from_formatted = date('F j, Y', strtotime($date_from));
        $date_to_formatted = date('F j, Y', strtotime($date_to));
        $date_range_text = $date_from_formatted . ' - ' . $date_to_formatted;
    } elseif (!empty($date_from)) {
        $date_from_formatted = date('F j, Y', strtotime($date_from));
        $date_range_text = 'From ' . $date_from_formatted;
    } elseif (!empty($date_to)) {
        $date_to_formatted = date('F j, Y', strtotime($date_to));
        $date_range_text = 'Until ' . $date_to_formatted;
    } else {
        // For "All Dates", show the actual date range from records
        if (!empty($summary['actual_min_date']) && !empty($summary['actual_max_date'])) {
            $min_formatted = date('F j, Y', strtotime($summary['actual_min_date']));
            $max_formatted = date('F j, Y', strtotime($summary['actual_max_date']));
            $date_range_text = $min_formatted . ' to ' . $max_formatted . ' (All Available Records)';
        }
    }
    
    // Format department filter
    $department_text = empty($department) ? 'All Departments' : htmlspecialchars($department);
    
    // Format total hours
    $total_hours_formatted = number_format($summary['total_hours_worked'], 2);
    
    // Calculate average daily rates for employees and get wage history
    $employee_avg_rates = [];
    $employee_wage_history = [];
    $employee_present_days = []; // Array to store present days per employee
    $employee_absent_days = []; // Array to store absent days per employee
    $employee_total_late = []; // Array to store total late minutes per employee
    $employee_late_deductions = []; // Array to store total late deductions per employee
    $employee_total_overtime = []; // Array to store total overtime minutes per employee
    $employee_overtime_pay = []; // Array to store total overtime pay per employee
    $employee_cash_advance = []; // Array to store cash advance totals per employee
    $employee_government_deductions = []; // Array to store government deductions per employee
    $employee_total_deductions = []; // Array to store total deductions per employee
    $employee_gross = []; // Array to store gross per employee
    $employee_net = []; // Array to store net per employee
    
    if ($pdo !== null) {
        // Get all employees
        $employee_sql = "SELECT id FROM employee";
        $employeeStmt = $pdo->prepare($employee_sql);
        $employeeStmt->execute();
        $all_employees = $employeeStmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Create a map of attendance records by employee and date
        $attendance_map = [];
        foreach ($records as $record) {
            $emp_id = $record['employee_db_id'];
            $date = $record['attendance_date'];
            if (!isset($attendance_map[$emp_id])) {
                $attendance_map[$emp_id] = [];
            }
            $attendance_map[$emp_id][$date] = $record;
        }
        
        // Generate all dates in the range
        $all_dates = [];
        $current_date = strtotime($summary['actual_min_date']);
        $end_date = strtotime($summary['actual_max_date']);
        while ($current_date <= $end_date) {
            $all_dates[] = date('Y-m-d', $current_date);
            $current_date = strtotime('+1 day', $current_date);
        }
        
        foreach ($all_employees as $emp_id) {
            $employee_present_days[$emp_id] = 0;
            $employee_absent_days[$emp_id] = 0;
            $employee_total_late[$emp_id] = 0;
            $employee_late_deductions[$emp_id] = 0;
            $employee_total_overtime[$emp_id] = 0;
            $employee_overtime_pay[$emp_id] = 0;
            
            // Calculate cash advance for this employee
            if (!empty($date_from) && !empty($date_to)) {
                $employee_cash_advance[$emp_id] = getEmployeeCashAdvance($pdo, $emp_id, $date_from, $date_to);
                
                // Get government deductions based on the effectivity date
                $employee_government_deductions[$emp_id] = getEmployeeGovernmentDeductions($pdo, $emp_id, $date_to);
            } else {
                $employee_cash_advance[$emp_id] = getEmployeeCashAdvance($pdo, $emp_id, $summary['actual_min_date'], $summary['actual_max_date']);
                
                // Get government deductions based on the effectivity date
                $employee_government_deductions[$emp_id] = getEmployeeGovernmentDeductions($pdo, $emp_id, $summary['actual_max_date']);
            }
            
            foreach ($all_dates as $date) {
                if (isset($attendance_map[$emp_id][$date])) {
                    $record = $attendance_map[$emp_id][$date];
                    $status = $record['status'] ?? 'Present';
                    
                    // Add late minutes and deductions for this employee
                    if (isset($record['late_time']) && $record['late_time'] > 0) {
                        $employee_total_late[$emp_id] += intval($record['late_time']);
                        
                        // Get daily rate for this date to calculate deduction
                        $daily_rate = getEmployeeWageOnDate($pdo, $emp_id, $date);
                        $late_deduction = calculateLateDeduction($daily_rate, intval($record['late_time']));
                        $employee_late_deductions[$emp_id] += $late_deduction;
                    }
                    
                    // Add overtime minutes and pay for this employee
                    if (isset($record['over_time']) && $record['over_time'] > 0) {
                        $employee_total_overtime[$emp_id] += intval($record['over_time']);
                        
                        // Get daily rate for this date to calculate overtime pay
                        $daily_rate = getEmployeeWageOnDate($pdo, $emp_id, $date);
                        $overtime_pay = calculateOvertimePay($daily_rate, intval($record['over_time']));
                        $employee_overtime_pay[$emp_id] += $overtime_pay;
                    }
                    
                    if ($status == 'Present') {
                        $employee_present_days[$emp_id] += 1;
                        $employee_absent_days[$emp_id] += 0;
                    } elseif ($status == 'Half Day') {
                        $employee_present_days[$emp_id] += 0.5;
                        $employee_absent_days[$emp_id] += 0.5; // Half day counts as 0.5 absent
                    } elseif ($status == 'Absent') {
                        $employee_present_days[$emp_id] += 0;
                        $employee_absent_days[$emp_id] += 1; // Absent counts as 1
                    }
                } else {
                    // No record for this date - employee is absent
                    $employee_absent_days[$emp_id] += 1; // No record = 1 absent
                }
            }
            
            // Calculate average rate based on present days
            $employee_avg_rates[$emp_id] = calculateAverageDailyRate($pdo, $emp_id, $summary['actual_min_date'], $summary['actual_max_date'], $attendance_map);
            $employee_wage_history[$emp_id] = getEmployeeWageHistory($pdo, $emp_id, $summary['actual_min_date'], $summary['actual_max_date']);
            
            // Calculate gross for this employee: (Avg Daily Rate * Present Days) + Overtime Pay
            $employee_gross[$emp_id] = ($employee_avg_rates[$emp_id] * $employee_present_days[$emp_id]) + $employee_overtime_pay[$emp_id];
            
            // Calculate total deductions for this employee
            $employee_total_deductions[$emp_id] = $employee_late_deductions[$emp_id] + 
                                                 $employee_cash_advance[$emp_id] + 
                                                 $employee_government_deductions[$emp_id]['sss'] + 
                                                 $employee_government_deductions[$emp_id]['pag_ibig'] + 
                                                 $employee_government_deductions[$emp_id]['philhealth'];
            
            // Calculate net for this employee
            $employee_net[$emp_id] = $employee_gross[$emp_id] - $employee_total_deductions[$emp_id];
        }
    }
    
    // Build HTML
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Payroll Report - OCP Construction</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                font-size: 9px;
                line-height: 1.2;
                margin: -10px -20px -10px -20px;
            }
            .company-header {
                text-align: center;
                font-weight: bold;
                font-size: 15pt;
                margin-bottom: 2px;
                letter-spacing: 1px;
                display: flex;
                align-items: center;
                justify-content: center;
                border-bottom: 2px solid #0d6efd;
            }
            .company-logo {
                width: 130px;
                height: 0 auto;
                vertical-align: middle;
                margin-right: 10px;
            }
            .company-name {
                display: inline-block;
                vertical-align: middle;
            }
            .company-name h1 {
                font-size: 24pt;
                margin: 0 0 5px 0;
            }
            .company-name h2 {
                font-size: 12pt;
                margin: 0 0 5px 0;
                font-weight: normal;
                color: #555;
            }
            .company-name p {
                margin: 3px 0;
                color: #666;
                font-size: 10pt;
            }
            .records-table {
                width: 100%;
                border-collapse: collapse;
                font-size: 8.5pt;
            }
            .records-table th {
                background-color: #0d6efd;
                color: white;
                font-weight: bold;
                padding: 6px 3px;
                text-align: center;
                border: 1px solid #0a58ca;
                white-space: nowrap;
            }
            .records-table td {
                padding: 4px 3px;
                border: 1px solid #dee2e6;
                vertical-align: top;
            }
            .records-table tbody tr:nth-child(even) {
                background-color: #f8f9fa;
            }
            .records-table tbody tr:hover {
                background-color: #e9ecef;
            }
            .late-time {
                color: #dc3545;
                font-weight: bold;
                text-align: center;
            }
            .over-time {
                color: #28a745;
                font-weight: bold;
                text-align: center;
            }
            .cash-advance, .sss-deduction, .pagibig-deduction, .philhealth-deduction, .total-deduction {
                color: #dc3545;
                font-weight: bold;
                text-align: center;
            }
            .gross {
                color: #0d6efd;
                font-weight: bold;
                text-align: center;
            }
            .net {
                font-weight: bold;
                text-align: center;
            }
            .signature {
                width: 130px;
            }
            .status-badge {
                display: inline-block;
                padding: 3px 8px;
                border-radius: 12px;
                font-size: 8pt;
                font-weight: bold;
                text-align: center;
                min-width: 60px;
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
            .text-center {
                text-align: center;
            }
            .text-right {
                text-align: right;
            }
            .font-bold {
                font-weight: bold;
            }
            .department-header {
                background-color: #e9ecef;
                font-weight: bold;
                padding: 8px;
                margin-top: 20px;
                border-left: 4px solid #0d6efd;
                font-size: 10pt;
            }
            .no-records {
                text-align: center;
                padding: 40px;
                color: #6c757d;
                font-style: italic;
                border: 1px dashed #dee2e6;
                border-radius: 5px;
                font-size: 11pt;
            }
            .currency {
                text-align: right;
                font-weight: 500;
            }
            .avg-rate-container {
                text-align: center;
            }
            .avg-rate-main {
                font-size: 10pt;
                font-weight: bold;
                color: #0d6efd;
            }
            .wage-history {
                font-size: 7pt;
                color: #6c757d;
                padding-top: 3px;
                text-align: left;
                line-height: 1.3;
                text-align: center;
            }
            .history-item {
                white-space: nowrap;
            }
            .history-date {
                font-weight: bold;
                color: #495057;
            }
            .late-deduction {
                color: #dc3545;
                font-size: 7pt;
                display: block;
            }
            .overtime-pay {
                color: #28a745;
                font-size: 7pt;
                display: block;
            }
            .emp-name {
                font-weight: bold;
            }
        </style>
    </head>
    <body>
        
        <div class="company-header">
            ' . $logo_html . '
            <span class="company-name">
                <h1>OCP CONSTRUCTION</h1>
                <h2>Payroll Attendance Report</h2>
                <p>Generated on: ' . date('F j, Y \a\t g:i A') . '</p>
                <p>Date Range: ' . $date_range_text . '</p>
                <p>Department: ' . $department_text . '</p>
            </span>
        </div>';
    
    if (empty($records)) {
        $html .= '<div class="no-records">No attendance records found for the selected criteria.</div>';
    } else {
        // Group records by department if no specific department filter
        if (empty($department)) {
            $grouped_records = [];
            foreach ($records as $record) {
                $dept = $record['department'] ?: 'No Department';
                $grouped_records[$dept][] = $record;
            }
            
            foreach ($grouped_records as $dept_name => $dept_records) {
                $html .= '<div class="department-header">Department: ' . htmlspecialchars($dept_name) . ' (' . count($dept_records) . ' records)</div>';
                $html .= generateRecordsTable($dept_records, $summary, $employee_avg_rates, $employee_wage_history, $employee_present_days, $employee_absent_days, $employee_total_late, $employee_late_deductions, $employee_total_overtime, $employee_overtime_pay, $employee_cash_advance, $employee_government_deductions, $employee_total_deductions, $employee_gross, $employee_net);
            }
        } else {
            $html .= generateRecordsTable($records, $summary, $employee_avg_rates, $employee_wage_history, $employee_present_days, $employee_absent_days, $employee_total_late, $employee_late_deductions, $employee_total_overtime, $employee_overtime_pay, $employee_cash_advance, $employee_government_deductions, $employee_total_deductions, $employee_gross, $employee_net);
        }
    }
    
    $html .= '
    </body>
    </html>';
    
    return $html;
}

/**
 * Generate records table HTML - Shows average daily rate based on present days with wage history below and present/absent days
 * Added OT Mins column with overtime pay, Cash Advance column, and SSS, Pag-IBIG, PhilHealth columns, Total Deduction column, Gross column, Net column, and Signature column
 */
function generateRecordsTable($records, $summary, $employee_avg_rates = [], $employee_wage_history = [], $employee_present_days = [], $employee_absent_days = [], $employee_total_late = [], $employee_late_deductions = [], $employee_total_overtime = [], $employee_overtime_pay = [], $employee_cash_advance = [], $employee_government_deductions = [], $employee_total_deductions = [], $employee_gross = [], $employee_net = []) {
    $html = '
    <table class="records-table">
        <thead>
            <tr>
                <th width="3%">#</th>
                <th width="11%">Employee Name</th>
                <th width="6%">Position</th>
                <th width="6%">Department</th>
                <th width="6%">Rate(Avg)</th>
                <th width="4%"># Days</th>
                <th width="4%">Present</th>
                <th width="4%">Absent</th>
                <th width="6%">Late Mins</th>
                <th width="6%">OT Mins</th>
                <th width="5%">CA</th>
                <th width="5%">SSS</th>
                <th width="5%">Pag-IBIG</th>
                <th width="5%">PhilHealth</th>
                <th width="6%">Total Deduct</th>
                <th width="6%">Gross</th>
                <th width="6%">Net</th>
                <th width="6%">Signature</th>
            </tr>
        </thead>
        <tbody>';
    
    $counter = 1;
    $unique_employees = [];
    
    foreach ($records as $record) {
        // Check if this employee name has already been displayed
        $employee_name = $record['employee_name'];
        $employee_db_id = $record['employee_db_id'];
        
        if (!in_array($employee_name, $unique_employees)) {
            // Add to unique employees array
            $unique_employees[] = $employee_name;
            
            // Get average daily rate based on present days (if available)
            $avg_daily_rate = isset($employee_avg_rates[$employee_db_id]) ? $employee_avg_rates[$employee_db_id] : 0;
            
            // If no average rate calculated, use the first daily rate from records
            if ($avg_daily_rate == 0 && isset($record['daily_rate'])) {
                $avg_daily_rate = floatval($record['daily_rate']);
            }
            
            // Get position, default to '-' if not set
            $position = isset($record['employee_position']) ? $record['employee_position'] : '-';
            
            // Get present days for this employee
            $present_days = isset($employee_present_days[$employee_db_id]) ? $employee_present_days[$employee_db_id] : 0;
            
            // Get absent days for this employee
            $absent_days = isset($employee_absent_days[$employee_db_id]) ? $employee_absent_days[$employee_db_id] : 0;
            
            // Get total late minutes for this employee
            $total_late_mins = isset($employee_total_late[$employee_db_id]) ? $employee_total_late[$employee_db_id] : 0;
            
            // Get total late deductions for this employee
            $total_late_deductions = isset($employee_late_deductions[$employee_db_id]) ? $employee_late_deductions[$employee_db_id] : 0;
            
            // Get total overtime minutes for this employee
            $total_overtime_mins = isset($employee_total_overtime[$employee_db_id]) ? $employee_total_overtime[$employee_db_id] : 0;
            
            // Get total overtime pay for this employee
            $total_overtime_pay = isset($employee_overtime_pay[$employee_db_id]) ? $employee_overtime_pay[$employee_db_id] : 0;
            
            // Get cash advance for this employee
            $cash_advance = isset($employee_cash_advance[$employee_db_id]) ? $employee_cash_advance[$employee_db_id] : 0;
            
            // Get government deductions for this employee
            $sss = 0;
            $pag_ibig = 0;
            $philhealth = 0;
            if (isset($employee_government_deductions[$employee_db_id])) {
                $sss = $employee_government_deductions[$employee_db_id]['sss'];
                $pag_ibig = $employee_government_deductions[$employee_db_id]['pag_ibig'];
                $philhealth = $employee_government_deductions[$employee_db_id]['philhealth'];
            }
            
            // Get total deductions for this employee
            $total_deductions = isset($employee_total_deductions[$employee_db_id]) ? $employee_total_deductions[$employee_db_id] : 0;
            
            // Get gross for this employee
            $gross = isset($employee_gross[$employee_db_id]) ? $employee_gross[$employee_db_id] : 0;
            
            // Get net for this employee
            $net = isset($employee_net[$employee_db_id]) ? $employee_net[$employee_db_id] : 0;
            
            // Build wage history HTML
            $wage_history_html = '';
            if (isset($employee_wage_history[$employee_db_id]) && !empty($employee_wage_history[$employee_db_id])) {
                $wage_history_html = '<div class="wage-history">';
                foreach ($employee_wage_history[$employee_db_id] as $history) {
                    $formatted_date = date('m/d/Y', strtotime($history['effectivity_date']));
                    $formatted_wage = number_format($history['new_wage'], 2);
                    $wage_history_html .= '<div class="history-item"><span class="history-date">' . $formatted_date . '</span>: ' . $formatted_wage . '</div>';
                }
                $wage_history_html .= '</div>';
            }
            
            // Format late minutes with deduction
            $late_display = '0 mins';
            if ($total_late_mins > 0) {
                $late_display = $total_late_mins . ' mins <span class="late-deduction">(' . number_format($total_late_deductions, 2) . ')</span>';
            } else {
                $late_display = '0 mins';
            }
            
            // Format overtime minutes with pay
            $overtime_display = '0 mins';
            if ($total_overtime_mins > 0) {
                $overtime_display = $total_overtime_mins . ' mins <span class="overtime-pay">(' . number_format($total_overtime_pay, 2) . ')</span>';
            } else {
                $overtime_display = '0 mins';
            }
            
            // Format cash advance (now with red color)
            $cash_advance_display = '0.00';
            if ($cash_advance > 0) {
                $cash_advance_display = number_format($cash_advance, 2);
            }
            
            // Format government deductions (now with red color)
            $sss_display = '0.00';
            if ($sss > 0) {
                $sss_display = number_format($sss, 2);
            }
            
            $pag_ibig_display = '0.00';
            if ($pag_ibig > 0) {
                $pag_ibig_display = number_format($pag_ibig, 2);
            }
            
            $philhealth_display = '0.00';
            if ($philhealth > 0) {
                $philhealth_display = number_format($philhealth, 2);
            }
            
            // Format total deductions
            $total_deductions_display = number_format($total_deductions, 2);
            
            // Format gross
            $gross_display = number_format($gross, 2);
            
            // Format net
            $net_display = number_format($net, 2);
            
            $html .= '
            <tr>
                <td class="text-center">' . $counter++ . '</td>
                <td class="emp-name">' . htmlspecialchars($employee_name) . '</td>
                <td>' . htmlspecialchars($position) . '</td>
                <td>' . htmlspecialchars($record['department'] ?: '-') . '</td>
                <td class="text-right">
                    <div class="avg-rate-container">
                        <span class="avg-rate-main">' . number_format($avg_daily_rate, 2) . '</span>
                        ' . $wage_history_html . '
                    </div>
                </td>
                <td class="text-center">' . $summary['work_days'] . '</td>
                <td class="text-center"><strong style="color: #28a745;">' . number_format($present_days, 1) . '</strong></td>
                <td class="text-center"><strong style="color: #dc3545;">' . number_format($absent_days, 1) . '</strong></td>
                <td class="text-center late-time">' . $late_display . '</td>
                <td class="text-center over-time">' . $overtime_display . '</td>
                <td class="text-center cash-advance">' . $cash_advance_display . '</td>
                <td class="text-center sss-deduction">' . $sss_display . '</td>
                <td class="text-center pagibig-deduction">' . $pag_ibig_display . '</td>
                <td class="text-center philhealth-deduction">' . $philhealth_display . '</td>
                <td class="text-center total-deduction">' . $total_deductions_display . '</td>
                <td class="text-center gross">' . $gross_display . '</td>
                <td class="text-center net">' . $net_display . '</td>
                <td class="text-center signature"></td>
            </tr>';
        }
    }
    
    $html .= '
        </tbody>
    </table>';
    
    return $html;
}

// Generate PDF
if (isset($_GET['download']) || !isset($_GET['preview'])) {
    // Create HTML content with PDO connection for average rate calculation
    $html_content = generatePDFHTML($attendance_records, $summary_stats, $date_from, $date_to, $department, $display_name, $pdo, $employee_wage_history, $employee_average_rates, $logo_html);
    
    // Configure DOMPDF options
    $options = new Options();
    $options->set('defaultFont', 'Arial');
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isPhpEnabled', true);
    $options->set('isRemoteEnabled', true);
    $options->set('defaultPaperSize', 'legal');
    $options->set('defaultPaperOrientation', 'landscape');
    $options->set('fontDir', __DIR__ . '/fonts/');
    $options->set('fontCache', __DIR__ . '/fonts/');
    $options->set('chroot', __DIR__);
    $options->set('dpi', 96);
    
    // Create DOMPDF instance
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html_content);
    
    // Set paper size and orientation
    $dompdf->setPaper('legal', 'landscape');
    
    // Render PDF
    $dompdf->render();
    
    // Generate filename
    $filename = 'payroll_report_';
    if (!empty($date_from) && !empty($date_to)) {
        $filename .= $date_from . '_to_' . $date_to;
    } else {
        $filename .= 'all_dates_' . date('Y-m-d');
    }
    if (!empty($department)) {
        $filename .= '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $department);
    }
    $filename .= '.pdf';
    
    // Output PDF
    $dompdf->stream($filename, array("Attachment" => isset($_GET['download'])));
    exit;
} else {
    // Preview mode - show HTML
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>Payroll PDF Preview - OCP Construction</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" />
        <style>
            body {
                background-color: #f8f9fa;
                padding: 20px;
            }
            .preview-container {
                max-width: 1800px;
                margin: 0 auto;
            }
            .preview-header {
                background-color: white;
                border-radius: 10px;
                padding: 20px;
                margin-bottom: 20px;
                box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            }
            .preview-actions {
                display: flex;
                gap: 10px;
                justify-content: flex-end;
            }
            .preview-content {
                background-color: white;
                border-radius: 10px;
                padding: 40px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                min-height: 800px;
                overflow-x: auto;
            }
            .btn-preview {
                padding: 10px 20px;
                font-size: 16px;
            }
            .btn-download {
                background-color: #28a745;
                border-color: #28a745;
                color: white;
            }
            .btn-download:hover {
                background-color: #218838;
                border-color: #1e7e34;
                color: white;
            }
        </style>
    </head>
    <body>
        <div class="preview-container">
            <div class="preview-header d-flex justify-content-between align-items-center">
                <h2 class="mb-0">Payroll Report Preview</h2>
                <div class="preview-actions">
                    <a href="generate_payroll_pdf.php?<?php echo http_build_query($_GET); ?>&download=1" class="btn btn-success btn-preview">
                        <i class="fas fa-download"></i> Download PDF
                    </a>
                    <a href="javascript:window.print()" class="btn btn-primary btn-preview">
                        <i class="fas fa-print"></i> Print
                    </a>
                    <a href="payroll.php" class="btn btn-secondary btn-preview">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </div>
            </div>
            <div class="preview-content">
                <?php echo generatePDFHTML($attendance_records, $summary_stats, $date_from, $date_to, $department, $display_name, $pdo, $employee_wage_history, $employee_average_rates, $logo_html); ?>
            </div>
        </div>
        
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
        <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js"></script>
    </body>
    </html>
    <?php
}
?>