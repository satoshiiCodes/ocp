/* gasoline_inventory.js
 * Extracted from gasoline_inventory.php - inline <script> block #1.
 * Server data arrives through window.OCP_PAGE_GASOLINE_INVENTORY
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
const GASOLINE_INVENTORY_DATA = window.OCP_PAGE_GASOLINE_INVENTORY || {};
            // Initialize DataTables
            window.addEventListener('DOMContentLoaded', event => {
                const inventoryTable = document.getElementById('inventoryTable');
                if (inventoryTable) {
                    new simpleDatatables.DataTable(inventoryTable, {
                        perPage: 10,
                        perPageSelect: [10, 25, 50, 100],
                        labels: {
                            placeholder: "Search...",
                            perPage: "entries per page",
                            noRows: "No entries to show",
                            info: "Showing {start} to {end} of {rows} entries"
                        }
                    });
                }
                
                const batchesTable = document.getElementById('batchesTable');
                if (batchesTable) {
                    new simpleDatatables.DataTable(batchesTable, {
                        perPage: 10,
                        perPageSelect: [10, 25, 50, 100],
                        labels: {
                            placeholder: "Search...",
                            perPage: "entries per page",
                            noRows: "No entries to show",
                            info: "Showing {start} to {end} of {rows} entries"
                        }
                    });
                }
                
                const movementsTable = document.getElementById('movementsTable');
                if (movementsTable) {
                    new simpleDatatables.DataTable(movementsTable, {
                        perPage: 10,
                        perPageSelect: [10, 25, 50, 100],
                        labels: {
                            placeholder: "Search...",
                            perPage: "entries per page",
                            noRows: "No entries to show",
                            info: "Showing {start} to {end} of {rows} entries"
                        }
                    });
                }
                
                // Show SweetAlert2 notifications
                // Tested here in the browser: this script is its own request, so the
                // page's variables are not in scope.
                if (GASOLINE_INVENTORY_DATA.hasMessage) {
                    Swal.fire({
                        title: GASOLINE_INVENTORY_DATA.swalDataTitle || '',
                        text: GASOLINE_INVENTORY_DATA.swalDataText || '',
                        icon: GASOLINE_INVENTORY_DATA.swalDataIcon || '',
                        confirmButtonText: 'OK'
                    });
                }
                
                // Show modal if there was an error with form submission
                // Tested here in the browser: this script is its own request, so the
                // page's variables are not in scope.
                if (GASOLINE_INVENTORY_DATA.isError && GASOLINE_INVENTORY_DATA.postedAction) {
                    // Which modal to reopen comes from the page's island: this script is its own
                    // request, so $_POST is not in scope here.
                    switch (GASOLINE_INVENTORY_DATA.postedAction) {
                        case 'gas_in':
                            var gasInModal = new bootstrap.Modal(document.getElementById('gasInModal'));
                            gasInModal.show();
                            break;
                        case 'gas_out':
                            var gasOutModal = new bootstrap.Modal(document.getElementById('gasOutModal'));
                            gasOutModal.show();
                            break;
                        case 'gas_transfer':
                            var transferModal = new bootstrap.Modal(document.getElementById('transferModal'));
                            transferModal.show();
                            break;
                        case 'set_min_gas':
                            var minGasModal = new bootstrap.Modal(document.getElementById('minGasModal'));
                            minGasModal.show();
                            break;
                        case 'initial_gas':
                            var initialGasModal = new bootstrap.Modal(document.getElementById('initialGasModal'));
                            initialGasModal.show();
                            break;
                    }
                }
                
                // Tank capacity validation for gas in
                document.getElementById('tank_id').addEventListener('change', function() {
                    const selectedOption = this.options[this.selectedIndex];
                    const capacity = parseFloat(selectedOption.getAttribute('data-capacity')) || 0;
                    const quantityInput = document.getElementById('quantity_liters');
                    
                    if (capacity > 0) {
                        quantityInput.setAttribute('max', capacity);
                    }
                });
                
                // Form validation for gas out - ensure at least one of vehicle or equipment is selected
                document.getElementById('gasOutForm').addEventListener('submit', function(e) {
                    const vehicleId = document.getElementById('vehicle_id').value;
                    const equipmentId = document.getElementById('equipment_id').value;
                    
                    if (!vehicleId && !equipmentId) {
                        e.preventDefault();
                        Swal.fire({
                            icon: 'error',
                            title: 'Validation Error',
                            text: 'Please select either a vehicle or equipment.'
                        });
                        return false;
                    }
                });
                
                // Disable Equipment field when Vehicle is selected and vice versa
                const vehicleSelect = document.getElementById('vehicle_id');
                const equipmentSelect = document.getElementById('equipment_id');
                
                if (vehicleSelect && equipmentSelect) {
                    vehicleSelect.addEventListener('change', function() {
                        if (this.value) {
                            equipmentSelect.disabled = true;
                            equipmentSelect.value = '';
                        } else {
                            equipmentSelect.disabled = false;
                        }
                    });
                    
                    equipmentSelect.addEventListener('change', function() {
                        if (this.value) {
                            vehicleSelect.disabled = true;
                            vehicleSelect.value = '';
                        } else {
                            vehicleSelect.disabled = false;
                        }
                    });
                }
            });
            
            // Logout function with SweetAlert2
            document.getElementById('logoutLink').addEventListener('click', function(e) {
                e.preventDefault();
                Swal.fire({
                    title: 'Are you sure?',
                    text: 'You want to logout from the system.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Yes, logout!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = 'actions/logout.php';
                    }
                });
            });
            
