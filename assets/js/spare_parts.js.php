/* spare_parts.js
 * Extracted from spare_parts.php - inline <script> block #1.
 * Server data arrives through window.OCP_PAGE_SPARE_PARTS
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
            // The page's data island. This script is fetched as its own request, so the
            // page's PHP variables are NOT in scope: the alert it prepared is read from
            // the island the page rendered.
            const SPARE_PARTS_DATA = window.OCP_PAGE_SPARE_PARTS || {};

            // The sidebar toggle is wired once, by assets/js/app.js. A second binding here made
            // one click toggle the class twice, so the menu never appeared to open.
            document.addEventListener('DOMContentLoaded', function() {
                
                // Initialize DataTables
                const datatablesSimple = document.getElementById('datatablesSimple');
                if (datatablesSimple) {
                    new simpleDatatables.DataTable(datatablesSimple);
                }
                
                // Handle edit modal data
                const editModal = document.getElementById('editPartModal');
                if (editModal) {
                    editModal.addEventListener('show.bs.modal', function (event) {
                        const button = event.relatedTarget;
                        const id = button.getAttribute('data-id');
                        const part_number = button.getAttribute('data-part_number');
                        const part_name = button.getAttribute('data-part_name');
                        const category_id = button.getAttribute('data-category_id');
                        const unit_of_measure = button.getAttribute('data-unit_of_measure');
                        const min_stock_level = button.getAttribute('data-min_stock_level');
                        
                        document.getElementById('edit_id').value = id;
                        document.getElementById('edit_part_number').value = part_number;
                        document.getElementById('edit_part_name').value = part_name;
                        document.getElementById('edit_category_id').value = category_id;
                        document.getElementById('edit_unit_of_measure').value = unit_of_measure;
                        document.getElementById('edit_min_stock_level').value = min_stock_level;
                    });
                }
                
                // Handle view modal data with proper date formatting
                const viewModal = document.getElementById('viewPartModal');
                if (viewModal) {
                    viewModal.addEventListener('show.bs.modal', function (event) {
                        const button = event.relatedTarget;
                        const part_number = button.getAttribute('data-part_number');
                        const part_name = button.getAttribute('data-part_name');
                        const category_name = button.getAttribute('data-category_name');
                        const unit_of_measure = button.getAttribute('data-unit_of_measure');
                        const min_stock_level = button.getAttribute('data-min_stock_level');
                        const created_at = button.getAttribute('data-created_at');
                        const updated_at = button.getAttribute('data-updated_at');
                        
                        document.getElementById('view_part_number').textContent = part_number || 'N/A';
                        document.getElementById('view_part_name').textContent = part_name || 'N/A';
                        document.getElementById('view_category_name').textContent = category_name || 'N/A';
                        document.getElementById('view_unit_of_measure').textContent = unit_of_measure || 'N/A';
                        document.getElementById('view_min_stock_level').textContent = min_stock_level || '0';
                        
                        // Set status based on min stock level.
                        // The classes are the design system's own: `bg-danger` / `bg-success` are
                        // Bootstrap names that no longer exist here, so the badge came out as
                        // unstyled text.
                        const minStock = parseInt(min_stock_level) || 0;
                        const statusElement = document.getElementById('view_status');
                        if (minStock <= 0) {
                            statusElement.innerHTML = '<span class="badge badge-danger">No Alert</span>';
                        } else {
                            statusElement.innerHTML = '<span class="badge badge-success">Alert Enabled (Min: ' + minStock + ')</span>';
                        }
                        
                        // Format dates to mm-dd-yyyy hh:mm:ss
                        function formatDate(dateString) {
                            if (!dateString) return 'N/A';
                            const date = new Date(dateString);
                            if (isNaN(date.getTime())) return 'N/A';
                            
                            const month = String(date.getMonth() + 1).padStart(2, '0');
                            const day = String(date.getDate()).padStart(2, '0');
                            const year = date.getFullYear();
                            const hours = String(date.getHours()).padStart(2, '0');
                            const minutes = String(date.getMinutes()).padStart(2, '0');
                            const seconds = String(date.getSeconds()).padStart(2, '0');
                            
                            return `${month}-${day}-${year} ${hours}:${minutes}:${seconds}`;
                        }
                        
                        document.getElementById('view_created_at').textContent = formatDate(created_at);
                        document.getElementById('view_updated_at').textContent = formatDate(updated_at);
                    });
                }
                
                // Handle delete buttons
                const deleteButtons = document.querySelectorAll('.delete-btn');
                deleteButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        const id = this.getAttribute('data-id');
                        const part_number = this.getAttribute('data-part_number');
                        const part_name = this.getAttribute('data-part_name');
                        
                        Swal.fire({
                            title: 'Are you sure?',
                            text: `You are about to delete the spare part "${part_name}". This action cannot be undone.`,
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#d33',
                            cancelButtonColor: '#3085d6',
                            confirmButtonText: 'Yes, delete it!',
                            cancelButtonText: 'Cancel',
                            showLoaderOnConfirm: true,
                            preConfirm: () => {
                                return new Promise((resolve) => {
                                    document.getElementById('delete_id').value = id;
                                    document.getElementById('deleteForm').submit();
                                    // The page will reload after form submission
                                });
                            }
                        });
                    });
                });
                
                // Show SweetAlert notifications from the page's data island.
                // Read here in the browser: this script is its own request, so it has no
                // session and the page's variables are not in scope. Echoing
                // $_SESSION['alert'] here produced an alert with no title, no text and no
                // icon - just its OK button - because the page had already consumed and
                // unset that key before the script printed.
                if (SPARE_PARTS_DATA.hasMessage) {
                    Swal.fire({
                        icon: SPARE_PARTS_DATA.sESSIONAlertType,
                        title: SPARE_PARTS_DATA.sESSIONAlertTitle,
                        text: SPARE_PARTS_DATA.sESSIONAlertMessage,
                        toast: false,
                        position: 'center',
                        showConfirmButton: true,
                        timer: null,
                        timerProgressBar: false
                    });
                }
                
                // Form validation with SweetAlert
                const addPartForm = document.getElementById('addPartForm');
                if (addPartForm) {
                    addPartForm.addEventListener('submit', function(e) {
                        const partNumber = document.getElementById('part_number').value.trim();
                        const partName = document.getElementById('part_name').value.trim();
                        const category = document.getElementById('category_id').value;
                        const minStock = document.getElementById('min_stock_level').value;
                        
                        if (!partNumber || !partName || !category) {
                            e.preventDefault();
                            Swal.fire({
                                icon: 'error',
                                title: 'Validation Error',
                                html: 'Please fill in all required fields:<br><br>' +
                                      '• Part Number<br>' +
                                      '• Part Name<br>' +
                                      '• Category',
                                confirmButtonText: 'OK'
                            });
                            return false;
                        }
                        
                        if (minStock && parseInt(minStock) < 0) {
                            e.preventDefault();
                            Swal.fire({
                                icon: 'error',
                                title: 'Validation Error',
                                text: 'Minimum stock level cannot be negative.',
                                confirmButtonText: 'OK'
                            });
                            return false;
                        }
                    });
                }
                
                const editPartForm = document.getElementById('editPartForm');
                if (editPartForm) {
                    editPartForm.addEventListener('submit', function(e) {
                        const partNumber = document.getElementById('edit_part_number').value.trim();
                        const partName = document.getElementById('edit_part_name').value.trim();
                        const category = document.getElementById('edit_category_id').value;
                        const minStock = document.getElementById('edit_min_stock_level').value;
                        
                        if (!partNumber || !partName || !category) {
                            e.preventDefault();
                            Swal.fire({
                                icon: 'error',
                                title: 'Validation Error',
                                html: 'Please fill in all required fields:<br><br>' +
                                      '• Part Number<br>' +
                                      '• Part Name<br>' +
                                      '• Category',
                                confirmButtonText: 'OK'
                            });
                            return false;
                        }
                        
                        if (minStock && parseInt(minStock) < 0) {
                            e.preventDefault();
                            Swal.fire({
                                icon: 'error',
                                title: 'Validation Error',
                                text: 'Minimum stock level cannot be negative.',
                                confirmButtonText: 'OK'
                            });
                            return false;
                        }
                    });
                }
                
                // Auto-close modals after successful form submission
                // Tested here in the browser: this script is its own request, so the
                // page's variables are not in scope.
                if (SPARE_PARTS_DATA.isSuccess) {
                const addModal = bootstrap.Modal.getInstance(document.getElementById('addPartModal'));
                if (addModal) addModal.hide();
                
                const editModalInstance = bootstrap.Modal.getInstance(document.getElementById('editPartModal'));
                if (editModalInstance) editModalInstance.hide();
                }
            });
