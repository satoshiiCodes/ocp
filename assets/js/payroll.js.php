/* payroll.js
 * Extracted from payroll.php - inline <script> block #1.
 * Server data arrives through window.OCP_PAGE_PAYROLL
 * (rendered by includes/page_data.php).
 *
 * This file is a PHP script that prints JavaScript: PHP and JavaScript were
 * interleaved inside string literals in the original inline block, so the
 * PHP islands are kept and their values are read from the page data island
 * ($__ocp_data). It therefore has to be served through PHP, which is why it
 * is named .js.php.
 */
<?php
/*
 * This script is generated per request from the page's data island, so a cached copy can outlive
 * the page it was built for: the HTML refreshes, the browser reuses the script, and the script
 * then reads a data island whose shape has moved on. That is how the dashboard charts once came
 * back blank - the page carried the corrected arrays while the browser replayed a script that
 * still expected the old ones.
 *
 * So it must not be cached, in either sense: the HTTP cache (no-store) and the back/forward store
 * (no-cache, must-revalidate).
 *
 * The headers are emitted only when this file is requested on its own. When the page prints it
 * inline, the response is the page and these headers would be meaningless there. They are sent
 * before the content-type below, which is the first header() call, so nothing is lost to output
 * already being on the wire.
 */
if (isset($_SERVER['SCRIPT_FILENAME'])
    && realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

/* Load the data-island helpers: walk up from this file until includes/ is found. */
if (!function_exists('ocp_js_raw')) {
    $__ocp_dir = __DIR__;
    for ($__ocp_i = 0; $__ocp_i < 6; $__ocp_i++) {
        if (is_file($__ocp_dir . '/includes/page_data.php')) {
            require_once $__ocp_dir . '/includes/page_data.php';
            break;
        }
        $__ocp_parent = dirname($__ocp_dir);
        if ($__ocp_parent === $__ocp_dir) {
            break;
        }
        $__ocp_dir = $__ocp_parent;
    }
    unset($__ocp_dir, $__ocp_i, $__ocp_parent);
}
/*
 * Printed as part of its page, $__ocp_data is already the data island and
 * every page variable this script reads is in scope.
 *
 * Requested on its own (a direct hit or a cache miss) there is no page, no
 * island and none of those variables, so notices are silenced, the island
 * starts empty, and missing members answer null. The response still parses as
 * JavaScript; it simply does nothing.
 */
if (!isset($__ocp_data)) {
    $__ocp_data = [];
    if (isset($_SERVER['SCRIPT_FILENAME'])
        && realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
        $GLOBALS['OCP_SCRIPT_STANDALONE'] = true;
        header('Content-Type: application/javascript; charset=utf-8');
        ini_set('display_errors', '0');
        error_reporting(0);
    }
}
?>
// The page's data island. This script is fetched as its own request, so the page's
// PHP variables are NOT in scope here: anything the page prepared is read from the
// island it rendered, in the browser.
const PAYROLL_DATA = window.OCP_PAGE_PAYROLL || {};
            // PHP message to JavaScript
            // Tested in the browser: this script is its own request, so the page's
            // variables are not available to it.
            if (PAYROLL_DATA.hasMessage) {
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: PAYROLL_DATA.messageType,
                    title: PAYROLL_DATA.messageType2,
                    html: PAYROLL_DATA.message,
                    timer: PAYROLL_DATA.messageType3,
                    timerProgressBar: true,
                    showConfirmButton: true
                });
            });
            }
            
            // Tested in the browser: this script is its own request, so the page's
            // variables are not available to it.
            if (PAYROLL_DATA.hasAttendanceError) {
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Database Error',
                    text: PAYROLL_DATA.attendanceError,
                    timer: 5000,
                    timerProgressBar: true
                });
            });
            }

            
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
                            perPage: "records per page",
                            noRows: "No records found",
                            info: "Showing {start} to {end} of {rows} records"
                        }
                    });
                }
            });
