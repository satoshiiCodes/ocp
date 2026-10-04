/* upload_attendance.js
 * Extracted from upload_attendance.php - inline <script> block #1.
 * Server data arrives through window.OCP_PAGE_UPLOAD_ATTENDANCE
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
const UPLOAD_ATTENDANCE_DATA = window.OCP_PAGE_UPLOAD_ATTENDANCE || {};
            // The page prepares this through a captured fragment, so it arrives as a JSON
            // string rather than an object - and when there is nothing to report it is the
            // string "null", which is truthy. Left raw, missingEmployeesData.employees was
            // undefined and this threw before the time fields were wired up, which is why
            // Late Time (minutes) never filled in.
            var missingEmployeesData =
                typeof UPLOAD_ATTENDANCE_DATA.missingEmployees === 'string'
                    ? JSON.parse(UPLOAD_ATTENDANCE_DATA.missingEmployees || 'null')
                    : (UPLOAD_ATTENDANCE_DATA.missingEmployees || null);
            
            // Standard cutoff times
            var CHECK_IN_CUTOFF = 8 * 60 + 15;   // 08:15 AM - on time up to 8:15, late starting at 8:16
            var BREAK_IN_CUTOFF = 13 * 60 + 15;  // 01:15 PM - on time up to 1:15, late starting at 1:16

            // Turns one <select> into a searchable dropdown.
            //
            // The original select is kept, hidden, so the form still posts employee_id and
            // anything that sets select.value still works. A text input above it filters the
            // options as the user types, which is what makes a list of every employee usable.
            function makeSearchableSelect(select) {
                var options = [];
                for (var i = 0; i < select.options.length; i++) {
                    var opt = select.options[i];
                    if (opt.value === '') continue;   // the "Select Employee" placeholder
                    options.push({ value: opt.value, label: opt.textContent.trim() });
                }

                var wrapper = document.createElement('div');
                wrapper.className = 'searchable-select';
                select.parentNode.insertBefore(wrapper, select);
                wrapper.appendChild(select);

                var input = document.createElement('input');
                input.type = 'text';
                input.className = 'searchable-select-input';
                input.setAttribute('autocomplete', 'off');
                input.setAttribute('role', 'combobox');
                input.setAttribute('aria-expanded', 'false');
                input.placeholder = select.id.indexOf('edit_') === 0 ? 'Search employee...' : 'Select Employee';
                wrapper.appendChild(input);

                var list = document.createElement('div');
                list.className = 'searchable-select-list';
                wrapper.appendChild(list);

                var matches = [];
                var active = -1;

                function labelFor(value) {
                    for (var k = 0; k < options.length; k++) if (options[k].value === String(value)) return options[k].label;
                    return '';
                }

                function close() {
                    list.classList.remove('show');
                    input.setAttribute('aria-expanded', 'false');
                    active = -1;
                }

                function choose(index) {
                    var pick = matches[index];
                    if (!pick) return;
                    select.value = pick.value;
                    input.value = pick.label;
                    close();
                }

                function render(term) {
                    var needle = (term || '').toLowerCase();
                    matches = needle === ''
                        ? options.slice()
                        : options.filter(function(o) { return o.label.toLowerCase().indexOf(needle) !== -1; });

                    if (!matches.length) {
                        list.innerHTML = '<div class="searchable-select-empty">No employee found</div>';
                    } else {
                        var html = '';
                        for (var k = 0; k < matches.length; k++) {
                            html += '<div class="searchable-select-item" data-index="' + k + '">'
                                + escapeHtml(matches[k].label) + '</div>';
                        }
                        list.innerHTML = html;
                    }
                    active = -1;
                    list.classList.add('show');
                    input.setAttribute('aria-expanded', 'true');
                }

                function highlight() {
                    var items = list.querySelectorAll('.searchable-select-item');
                    for (var k = 0; k < items.length; k++) items[k].classList.toggle('active', k === active);
                    if (active >= 0 && items[active] && items[active].scrollIntoView) {
                        items[active].scrollIntoView({ block: 'nearest' });
                    }
                }

                input.addEventListener('input', function() { render(this.value); });
                input.addEventListener('focus', function() { render(this.value); });

                input.addEventListener('keydown', function(e) {
                    if (!list.classList.contains('show')) {
                        if (e.key === 'ArrowDown') { render(this.value); e.preventDefault(); }
                        return;
                    }
                    if (e.key === 'ArrowDown') {
                        active = Math.min(active + 1, matches.length - 1);
                        highlight();
                        e.preventDefault();
                    } else if (e.key === 'ArrowUp') {
                        active = Math.max(active - 1, 0);
                        highlight();
                        e.preventDefault();
                    } else if (e.key === 'Enter') {
                        if (active >= 0) { choose(active); e.preventDefault(); }
                    } else if (e.key === 'Escape') {
                        close();
                    }
                });

                list.addEventListener('mousedown', function(e) {
                    var item = e.target.closest ? e.target.closest('.searchable-select-item') : null;
                    if (!item) return;
                    e.preventDefault();               // keep focus so blur does not close first
                    choose(parseInt(item.getAttribute('data-index'), 10));
                });

                input.addEventListener('blur', function() {
                    setTimeout(function() {
                        close();
                        // an unmatched term would otherwise leave the old value posted
                        if (select.value && input.value !== labelFor(select.value)) {
                            input.value = labelFor(select.value);
                        }
                        if (!select.value) input.value = '';
                    }, 150);
                });

                // the page sets select.value to prefill the edit modal - keep the box in step
                select.addEventListener('change', function() {
                    input.value = labelFor(select.value);
                });

                input.value = labelFor(select.value);

                // so the page can re-sync after setting select.value directly
                select.ocpSyncSearchableSelect = function() {
                    input.value = labelFor(select.value);
                };
            }
            
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
                    var editEmployee = document.getElementById('edit_employee_id');
                    editEmployee.value = recordData.employee_id || '';
                    // setting the select directly does not fire change, so tell the searchable
                    // wrapper to show the employee it now holds
                    if (editEmployee.ocpSyncSearchableSelect) editEmployee.ocpSyncSearchableSelect();
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
                // Tested in the browser: this script is its own request, so the page's
                // variables are not available to it.
                if (UPLOAD_ATTENDANCE_DATA.hasMessage) {
                Swal.fire({ icon: UPLOAD_ATTENDANCE_DATA.messageType, title: UPLOAD_ATTENDANCE_DATA.messageType2, html: UPLOAD_ATTENDANCE_DATA.message, timer: UPLOAD_ATTENDANCE_DATA.messageType3, timerProgressBar: true, showConfirmButton: true });
                }
                
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
                
                // Tested in the browser: this script is its own request, so the page's
                // variables are not available to it.
                if (UPLOAD_ATTENDANCE_DATA.hasAttendanceError) {
                Swal.fire({ icon: 'error', title: 'Database Error', text: UPLOAD_ATTENDANCE_DATA.attendanceError, timer: 5000 });
                }
                
                // The "no records" notice is also answered by the island: this script is a
                // separate request, so the test cannot be made with the page's variables.
                if (UPLOAD_ATTENDANCE_DATA.showNoRecords) {
                    Swal.fire({ icon: 'info', title: 'No Records', text: 'No attendance records for this date range.', timer: 3000 });
                }
                
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

                // The Employee fields: a plain <select> with one option per employee becomes a
                // text input that filters as you type. The original select stays in the form
                // (hidden by the stylesheet), so the POST is unchanged.
                ['modal_employee_id', 'edit_employee_id'].forEach(function(id) {
                    var select = document.getElementById(id);
                    if (select) makeSearchableSelect(select);
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
                document.getElementById('modal_attendance_date').value = UPLOAD_ATTENDANCE_DATA.today || '';
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
