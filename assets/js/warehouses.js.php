/* warehouses.js
 * Extracted from warehouses.php - inline <script> block #1.
 * Server data arrives through window.OCP_PAGE_WAREHOUSES
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
            // The page's data island. This script is its own request, so the page's
            // PHP variables are NOT in scope: everything it needs is read from the
            // island the page rendered.
            const WAREHOUSES_DATA = window.OCP_PAGE_WAREHOUSES || {};
            
            // Initialize DataTables
            window.addEventListener('DOMContentLoaded', event => {
                const datatablesSimple = document.getElementById('datatablesSimple');
                if (datatablesSimple) {
                    new simpleDatatables.DataTable(datatablesSimple);
                }
                
                // Show SweetAlert if there's a message.
                // Tested here in the browser: this script is its own request, so the page's
                // $swal_data and $_POST are not available to it.
                if (WAREHOUSES_DATA.hasMessage) {
                    Swal.fire({
                        title: WAREHOUSES_DATA.swalDataTitle,
                        text: WAREHOUSES_DATA.swalDataText,
                        icon: WAREHOUSES_DATA.swalDataIcon,
                        confirmButtonText: 'OK'
                    });
                }
                
                // Show add modal if there was an error with form submission
                if (WAREHOUSES_DATA.reopenAddModal) {
                    var addWarehouseModal = new bootstrap.Modal(document.getElementById('addWarehouseModal'));
                    addWarehouseModal.show();
                }
                
                // Handle view button click
                document.querySelectorAll('.view-btn').forEach(button => {
                    button.addEventListener('click', function() {
                        const warehouse = JSON.parse(this.getAttribute('data-warehouse'));
                        
                        document.getElementById('view_warehouse_name').textContent = warehouse.warehouse_name;
                        document.getElementById('view_location').textContent = warehouse.location;
                        document.getElementById('view_capacity').textContent = warehouse.capacity || 'N/A';
                        document.getElementById('view_manager').textContent = warehouse.manager || 'N/A';
                        document.getElementById('view_phone').textContent = warehouse.phone || 'N/A';
                        
                        var viewModal = new bootstrap.Modal(document.getElementById('viewWarehouseModal'));
                        viewModal.show();
                    });
                });
                
                // Handle edit button click
                document.querySelectorAll('.edit-btn').forEach(button => {
                    button.addEventListener('click', function() {
                        const warehouse = JSON.parse(this.getAttribute('data-warehouse'));
                        
                        document.getElementById('edit_id').value = warehouse.id;
                        document.getElementById('edit_warehouse_name').value = warehouse.warehouse_name;
                        document.getElementById('edit_location').value = warehouse.location;
                        document.getElementById('edit_capacity').value = warehouse.capacity || '';
                        document.getElementById('edit_manager').value = warehouse.manager || '';
                        document.getElementById('edit_phone').value = warehouse.phone || '';
                        
                        var editModal = new bootstrap.Modal(document.getElementById('editWarehouseModal'));
                        editModal.show();
                    });
                });
                
                // Show edit modal if there was an error with update
                if (WAREHOUSES_DATA.reopenEditModal) {
                    var editWarehouseModal = new bootstrap.Modal(document.getElementById('editWarehouseModal'));
                    editWarehouseModal.show();
                    
                    // Pre-fill the form with submitted values
                    document.getElementById('edit_id').value = WAREHOUSES_DATA.pOST;
                    document.getElementById('edit_warehouse_name').value = WAREHOUSES_DATA.pOST2;
                    document.getElementById('edit_location').value = WAREHOUSES_DATA.pOST3;
                    document.getElementById('edit_capacity').value = WAREHOUSES_DATA.pOST4;
                    document.getElementById('edit_manager').value = WAREHOUSES_DATA.pOST5;
                    document.getElementById('edit_phone').value = WAREHOUSES_DATA.pOST6;
                }
            });
            
            // Custom confirmation for delete using SweetAlert2
            function confirmDelete(event, form) {
                event.preventDefault();
                
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
