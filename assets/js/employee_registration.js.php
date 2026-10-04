/* employee_registration.js
 * Extracted from employee_registration.php - inline <script> block #1.
 * Server data arrives through window.OCP_PAGE_EMPLOYEE_REGISTRATION
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
const EMPLOYEE_REGISTRATION_DATA = window.OCP_PAGE_EMPLOYEE_REGISTRATION || {};
        function showDeductionSection(type) {
            // Hide all sections
            document.getElementById('cash_advance_section').style.display = 'none';
            document.getElementById('sss_section').style.display = 'none';
            document.getElementById('pag_ibig_section').style.display = 'none';
            document.getElementById('philhealth_section').style.display = 'none';
            
            // Show selected section
            if (type) {
                document.getElementById(type + '_section').style.display = 'block';
            }
        }
        
        // Show section based on existing data on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Tested in the browser: this script is its own request, so the page's
            // variables are not available to it.
            if (EMPLOYEE_REGISTRATION_DATA.openEditDeductions) {
                // Which deduction this is comes from the page's island: this script is a
                // separate request, so $edit_deductions_data is not in scope here.
                const deductionKind = EMPLOYEE_REGISTRATION_DATA.deductionKind;
                if (deductionKind) {
                    showDeductionSection(deductionKind);
                    document.getElementById('deduction_type_selector').value = deductionKind;
                }
            }
        });

/* ------------------------------------------------------------------
 * Second inline <script> block of employee_registration.php, merged in
 * so this page has a single script file. It reuses the island built
 * above, so it carries no bootstrap of its own.
 * ------------------------------------------------------------------ */
            
            // Function to show SweetAlert
            function showSweetAlert(icon, title, text) {
                Swal.fire({
                    icon: icon,
                    title: title,
                    text: text,
                    timer: 3000,
                    showConfirmButton: true
                });
            }
            
            // Function to confirm delete with SweetAlert
            function confirmDelete(event) {
                event.preventDefault();
                const form = event.target.closest('form');
                
                Swal.fire({
                    title: 'Are you sure?',
                    text: "You won't be able to revert this!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Yes, delete it!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        form.submit();
                    }
                });
            }
            
            document.addEventListener('DOMContentLoaded', function() {
                // Initialize DataTables
                const employeeTable = new simpleDatatables.DataTable("#employeeTable", {
                    searchable: true,
                    fixedHeight: false,
                    perPage: 10,
                    columns: [
                        { select: 0, sortable: true },
                        { select: 1, sortable: true },
                        { select: 2, sortable: true },
                        { select: 3, sortable: true },
                        { select: 4, sortable: false },
                        { select: 5, sortable: true },
                        { select: 6, sortable: false }
                    ]
                });
                
                // Show SweetAlert if there's a message
                // Tested in the browser: this script is its own request, so the page's
                // variables are not available to it.
                if (EMPLOYEE_REGISTRATION_DATA.hasMessage) {
                    showSweetAlert(EMPLOYEE_REGISTRATION_DATA.swalDataIcon, EMPLOYEE_REGISTRATION_DATA.swalDataTitle, EMPLOYEE_REGISTRATION_DATA.swalDataText);
                }
                
                // If there was a form submission error, show the appropriate modal
                // Tested in the browser: this script is its own request, so the page's
                // variables are not available to it.
                if (EMPLOYEE_REGISTRATION_DATA.isError) {
                    // Which modal to reopen is decided by the page and handed over in the
                    // island: this script is a separate request, so $_POST is not in scope.
                    switch (EMPLOYEE_REGISTRATION_DATA.postedForm) {
                        case 'edit_deductions':
                            const deductionsModal = new bootstrap.Modal(document.getElementById('editDeductionsModal'));
                            deductionsModal.show();
                            break;
                        case 'edit_details':
                            const editModal = new bootstrap.Modal(document.getElementById('editEmployeeModal'));
                            editModal.show();
                            break;
                        case 'edit_wage':
                            const wageModal = new bootstrap.Modal(document.getElementById('editWageModal'));
                            wageModal.show();
                            break;
                        default:
                            // No edit form was submitted, so this was an add that failed
                            if (!EMPLOYEE_REGISTRATION_DATA.postedIsUpdate) {
                                const addModal = new bootstrap.Modal(document.getElementById('addEmployeeModal'));
                                addModal.show();
                            }
                    }
                }
            });
